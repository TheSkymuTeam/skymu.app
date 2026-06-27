# Skymu Plugin Development Guide

We want it to be easy, convenient, and enjoyable to create Skymu plugins, so we've created this documentation for new and experienced developers alike. Whether you're just getting started or you're porting an existing plugin from the old MiddleMan architecture, this guide walks you through everything you need to know.

## Overview

Yggdrasil is the plugin interface library for Skymu. It replaces the older MiddleMan library and introduces a redesigned architecture with a cleaner event model, richer data types, and a better overall separation of concerns between what the plugin does and what Skymu takes care of. If you have previously written a plugin for MiddleMan, most of the concepts will feel familiar, but the specifics have changed substantially enough that reading this guide in full is recommended before you begin porting.

The library is distributed as a single DLL called `Yggdrasil.dll`. Your plugin references it as a dependency, but you do not need to ship it alongside your compiled plugin. Skymu provides its own copy at runtime and uses it to load and talk to your plugin. This means that if the version of Yggdrasil bundled with Skymu changes, your plugin will automatically benefit from any fixes or improvements, as long as the interface signatures remain compatible.

## Namespaces

Yggdrasil is split across several namespaces, each with a specific purpose. You will import whichever ones your plugin needs.

`Yggdrasil` is the root namespace and contains the four interfaces your plugin can implement: `ICore`, `ICall`, `IListManagement`, and `IExtras`.

`Yggdrasil.Models` contains all of the data classes you will work with directly, such as `User`, `Message`, `DirectMessage`, `Group`, `Server`, `ServerChannel`, `Attachment`, `SavedCredential`, and so on.

`Yggdrasil.Bottles` contains the event argument classes used by the tube system: `DialogBottle`, `MessageBottle` and its subtypes, `ListBottle` and its subtypes, and `CallBottle`.

`Yggdrasil.Enumerations` contains all enums used by the library, including `LoginResult`, `PresenceStatus`, `AuthenticationMethod`, `ChannelType`, `Fetch`, `CallState`, `AttachmentType`, and others.

`Yggdrasil.Networking` contains optional networking helpers, namely `BifrostEngine` and `BifrostWebSocket`, which are TLS implementations built on Bouncy Castle rather than Windows Schannel.

`Yggdrasil.Tools.Windows` contains `LibraryHelper`, a utility for loading native DLLs from architecture-specific subfolders.

## Interfaces

An interface is a formal contract that specifies exactly what properties, methods, and events your plugin class must provide. Skymu uses these contracts to interact with your plugin without knowing anything about the protocol you are implementing underneath. Because both sides agree on the same contract, a plugin for any protocol can slot into Skymu the same way.

Yggdrasil defines four interfaces. Every plugin must implement `ICore`, which covers the essentials: authentication, fetching conversations and contacts, sending and receiving messages, and managing user presence. The remaining three interfaces are optional and can be added to the same class as your `ICore` implementation.

`ICall` should be implemented if your protocol supports voice or video calls. It gives Skymu the ability to start, answer, decline, and end calls through your plugin, and gives your plugin a way to notify Skymu of incoming calls and call state changes.

`IListManagement` should be implemented if your protocol supports searching for new contacts or conversations and adding them. It covers the two-step flow of finding something and then actually adding it.

`IExtras` should be implemented if you want to expose custom actions to the user through Skymu's extras menu. Each action is just a label and a callback, so this is a lightweight way to surface protocol-specific functionality that does not fit anywhere else in the UI.

Your plugin class can implement any combination of these interfaces, and Skymu will check which ones are present at load time to decide which features to enable.

## The Tubes and Bottles System

In MiddleMan, the plugin communicated back to Skymu through just two events (`OnError` and `OnWarning`) and a handful of `ObservableCollection` properties that the UI bound to directly. This was simple but inflexible. Yggdrasil replaces it with a general-purpose event system referred to as tubes and bottles.

A tube is simply an event declared on your plugin class. A bottle is the event argument object you construct and fire into it. The naming is intentional: you load a bottle into a tube, and Skymu receives it on the other end. This framing helps distinguish Yggdrasil's event arguments from ordinary .NET event args, and it makes the code read more naturally once you are used to it.

`ICore` requires three tubes. `DialogTube` is how your plugin asks Skymu to show a dialog to the user. This replaces both `OnError` and `OnWarning` from MiddleMan, and also adds support for informational dialogs and yes/no question dialogs. `MessageTube` is how your plugin notifies Skymu that a message was received, edited, or deleted in a conversation. `ListTube` is how your plugin notifies Skymu that an entry was added to, updated in, or removed from one of the lists Skymu manages, such as the contacts list or the conversations list.

If you implement `ICall`, two more tubes are required. `IncomingCallTube` is used to notify Skymu that an incoming call has arrived. `CallStateChangedTube` is used to signal that the state of a call has changed, for example because the remote party accepted, declined, or hung up.

Every tube is declared as a standard .NET event on your class. Skymu subscribes to them at load time. When you want to communicate something to the frontend, you construct the appropriate bottle and invoke the tube. If no one has subscribed yet, the event will be null, so always use the null-conditional `?.Invoke` pattern when firing tubes.

## Getting Started

### Requirements

Before you can start writing a plugin, you will need Visual Studio 2019 or later (the Community edition works fine), the .NET desktop development workload, and a copy of `Yggdrasil.dll` from either the Skymu source repository or a Skymu binary distribution. A working understanding of C# and asynchronous programming with `async`/`await` is assumed throughout this guide.

### Project Setup

Start by creating a new Class Library project in Visual Studio. You can target .NET Framework 4.6.1 (and newer), .NET Standard 2.0 (and newer), or .NET Core 5 (and newer), however .NET Standard 2.0 is recommended for maximum compatibility. Once the project is created, add a reference to `Yggdrasil.dll` by right-clicking References in the Solution Explorer, selecting Add Reference, and browsing to the file on disk.

With the reference in place, create your main plugin class and declare that it implements `ICore`. Visual Studio will immediately tell you that several members are not implemented; you can use the quick-fix to generate stubs for all of them, which gives you a good starting skeleton to fill in. Add `ICall`, `IListManagement`, and `IExtras` to the class declaration if your protocol needs them, and generate stubs for those as well.

### Chapter One: *Plugin Identity*

The first things to implement are the properties that tell Skymu who your plugin is. `Name` should return a human-readable display name for the protocol, such as "Discord" or "MyFancyChat". `InternalName` should return a stable, machine-friendly identifier with no spaces or uppercase letters, such as `"skymu-myfancychat-plugin"`. This internal name is used in logging and in the credential database, so once you choose it, do not change it between releases or saved credentials will be orphaned.

`SupportsServers` should return `true` if your protocol has a concept analogous to Discord servers or Slack workspaces, meaning communities that contain multiple channels which are themselves conversation spaces. If your protocol only has direct messages and group chats, return `false`. `TypingTimeout` and `TypingRepeat` control the typing indicator behaviour: `TypingTimeout` is how long, in milliseconds, a typing indicator stays visible after the last typing signal, and `TypingRepeat` is how often, in milliseconds, your plugin should re-send the typing signal to the server to keep the indicator alive while the user is still typing.

### Chapter Two: *Authentication*

Authentication is the most protocol-specific part of any plugin, but the structure Yggdrasil expects is consistent regardless of how your protocol actually works underneath.

Start by declaring the authentication methods your plugin supports through the `AuthenticationTypes` property. This returns an array of `AuthTypeInfo` objects, each of which wraps an `AuthenticationMethod` enum value. The `AuthTypeInfo` constructor also accepts optional strings to customise the label above the username field and the label on the authentication method selector in the login UI. If your protocol uses email addresses instead of usernames, for example, you can pass `"Email"` as the custom username field text so the label in the UI reflects that.

The main login method is `Authenticate(AuthenticationMethod authType, string username, string password)`. This is called when the user clicks sign in. Implement your login logic here and return `LoginResult.Success` if it works. If something goes wrong, fire `DialogTube` with a `DialogBottle` describing the problem and return `LoginResult.Failure`. If your protocol requires a second factor such as a TOTP code, return `LoginResult.TwoFARequired` and Skymu will call `AuthenticateTwoFA(string code)` next with whatever the user enters.

Once a login succeeds, Skymu will call `StoreCredential()`. This is your opportunity to capture whatever is needed to restore the session later, typically an access token rather than the raw password. Return a `SavedCredential` object containing the logged-in `User`, the token or password string, the `AuthenticationMethod` that was used, and your `InternalName`. Skymu stores this in its credential database.

When the user has a saved session and opens Skymu again, the other `Authenticate` overload is called: `Authenticate(SavedCredential credential)`. Restore your session from the credential object here and return `LoginResult.Success` if it works, or `LoginResult.Failure` if the token has expired and the user needs to log in manually again.

If your protocol supports QR code authentication, implement `GetQRCode()` to return a string that can be used to generate a QR code for display in the login screen. This method is only called if `AuthenticationTypes` includes `AuthenticationMethod.QRCode`.

### Chapter Three: *User Information*

After a successful login, Skymu calls `GetUserInfo()`. Return a `User` object representing the currently logged-in account. This is a good moment to capture a `SynchronizationContext` from `SynchronizationContext.Current` if you plan to make UI-thread updates later, for example when modifying `TypingUsersList` from a background WebSocket loop. (If having to do this worries you, TypingUsersList will be replaced by a tube/bottle pair in the future) 

The `User` class has a `DisplayName`, a `Username`, an `Identifier`, an optional `Status` string for the user's text mood, a `ConnectionStatus` of type `PresenceStatus`, and an optional `ProfilePicture` as a byte array. Fill in as many of these as your protocol provides. The identifier should be a stable unique ID from the protocol, not the display name, since display names can change.

### Chapter Four: *Conversations and Contacts*

Once Skymu has the user info, it will call `FetchConversations()` and `FetchContacts()`. These methods give Skymu the initial lists it needs to populate the UI.

`FetchConversations()` returns a `List<Conversation>`. `Conversation` is an abstract type with two concrete implementations. `DirectMessage` represents a one-on-one conversation with another user and is constructed with a `User` object representing the other participant, an unread count, a conversation identifier, and an optional last message timestamp. `Group` represents a multi-person group chat and is constructed with a name, an identifier, an unread count, a `User[]` array of members, an optional profile picture, and an optional last message timestamp.

`FetchContacts()` returns a `List<DirectMessage>`. This is the contacts list as opposed to the recent conversations list. On many protocols these are the same thing or nearly so; on others, contacts are explicitly added and the conversations list is derived from message history. Return whichever makes sense for your protocol.

If `SupportsServers` is `true`, Skymu will also call `FetchServers()`. Return a `List<Server>` where each `Server` has a name, an identifier, an optional member array, and a `ServerChannel[]` array. Each `ServerChannel` specifies its `ChannelType`, which tells Skymu whether the channel is a normal read/write channel, a read-only channel, an announcement channel, a voice channel, a restricted channel, a channel the user cannot access at all, or a forum-style channel. If your protocol organises channels into categories, populate the `CategoryMap` dictionary on the `Server` object with category ID to category name mappings, and set the `CategoryID` on each `ServerChannel` accordingly.

After the initial fetch, use `ListTube` to push real-time updates as your WebSocket or polling loop delivers them. Fire a `ListItemUpdatedBottle` when a contact or conversation is added or updated, and a `ListItemRemovedBottle` when one is removed. Pass the appropriate `ListType` value so Skymu knows which list to update.

### Chapter Five: *Messages*

`FetchMessages(Conversation conversation, Fetch fetchType, int messageCount, string identifier)` is called whenever Skymu needs to load messages for a conversation. The `Fetch` enum tells you in which direction to load and from which anchor point.

`Fetch.Newest` means load the most recent messages. `Fetch.Oldest` means load from the beginning of the conversation history. `Fetch.BeforeIdentifier` and `Fetch.AfterIdentifier` mean load relative to the message ID passed in the `identifier` parameter, which is used for pagination as the user scrolls. `Fetch.NewestAfterIdentifier` means load the newest batch of messages but only those that come after the given identifier, which is useful for catching up after a gap.

Return a `List<ConversationItem>`. Most items will be `Message` objects, but the list can also include `ActionMessage` for action-style messages such as those produced by `/me` commands, `CallStartedNotice` for call start events, and `CallEndedNotice` for call end events. All of these extend `ConversationItem`, which has a `Time`, an `Identifier`, and a `ConversationId`.

A `Message` carries a `User Author`, a `Text` string, an optional `Attachment[]` array, an optional `ParentMessage` for reply threading, an `IsForwarded` flag, and the inherited time and identifier fields. The `Author` should be a full `User` object with display name and identifier filled in. Because Skymu does not maintain a separate identity resolution layer for all participants in a conversation, the author information is expected to be self-contained in each message object.

### Chapter Six: *Sending Messages*

`SendMessage(string conversationId, string text, Attachment attachment, string parentMessageId, bool action)` is called when the user sends a message. The `text` and `attachment` parameters are both nullable, since a message could be text only, attachment only, or both. The `parentMessageId` parameter is set when the message is a reply to an existing message. The `action` boolean indicates that the message should be sent as an action message rather than a regular one.

Send the message to your protocol's API, and once it is confirmed sent, fire `MessageTube` with a `MessageRecievedBottle` containing the conversation ID, a `Message` or `ActionMessage` object, and a boolean indicating whether the message was sent in a server channel rather than a direct or group conversation. Firing this bottle is what actually adds the message to the UI. Return `true` from `SendMessage` if the send was successful, or `false` and an appropriate `DialogBottle` if it was not.

`EditMessage(string conversationId, string messageId, string newText)` and `DeleteMessage(string conversationId, string messageId)` follow the same pattern. For edits, fire `MessageTube` with a `MessageEditedBottle` containing the old message ID and the new `ConversationItem`. For deletions, fire `MessageTube` with a `MessageDeletedBottle` containing the deleted message ID. If your protocol does not support editing or deletion, simply return `false` from the relevant method and optionally show a `DialogBottle` explaining this.

### Chapter Seven: *Real-Time Updates*

Once the initial data is loaded, most of the work your plugin does will be driven by incoming events from the protocol, arriving over a WebSocket or a polling loop. The tube system is your primary tool for this.

For incoming messages, fire `MessageTube` with the appropriate bottle type. A newly arrived message uses `MessageRecievedBottle`. An edit to an existing message uses `MessageEditedBottle`, which takes the old message ID and the full new `ConversationItem` as replacement. A deletion uses `MessageDeletedBottle`, which just needs the deleted message's ID.

For typing indicators, update `TypingUsersList` directly by adding `User` objects when typing starts and removing them when it stops. This collection is an `ObservableCollection<User>` and Skymu binds to it directly, so changes are reflected in the UI immediately. Because this collection must be modified on the UI thread, use the `SynchronizationContext` you captured in `GetUserInfo()` to post updates from your background thread.

For presence changes, update the `ConnectionStatus` and `Status` properties on the relevant `User` objects you returned earlier. Because `User` implements `INotifyPropertyChanged`, Skymu's data bindings will pick up the changes automatically without you needing to fire any tubes.

For list changes such as a new conversation appearing or a contact being removed, fire `ListTube` with the appropriate bottle. Use `ListItemUpdatedBottle` for additions and updates, and `ListItemRemovedBottle` for removals, passing the relevant `ListType` in each case.

### Chapter Eight: *Presence and Mood*

`SetConnectionStatus(PresenceStatus status)` is called when the user changes their own presence status through the Skymu UI. Forward the change to your protocol's API and return `true` on success.

`SetMood(string status)` is called when the user changes their text status. Again, forward it to the API and return `true` on success.

`SetTyping(string identifier, bool typing)` is called by Skymu on a timer while the user is composing a message. The `identifier` is the conversation identifier. The `TypingTimeout` and `TypingRepeat` properties you set earlier control how often this is called and how long the typing indicator lasts. Forward the typing signal to your protocol's API if it supports this feature, and return `true` on success or `false` if the protocol does not support typing indicators.

### Chapter Nine: *Cleanup*

`Dispose()` is called when the user signs out. Stop any background loops, cancel any pending tasks, close any WebSocket connections, and release any other resources your plugin holds. Leaving background threads or timers running after `Dispose()` will cause problems, so be thorough here.

### Building and Testing

Once you have implemented all required members, build the project. A successful compile means your plugin satisfies the interface contract. Copy the resulting DLL to Skymu's plugins folder, which is typically in the application directory, and launch Skymu. Your plugin should appear in the protocol selection list on the login screen.

For testing, work through the features in the same order you implemented them. Confirm that authentication succeeds and that `GetUserInfo` returns sensible data. Then verify that conversations and contacts load correctly. Open a conversation and check that messages load and display properly. Send a message and verify that it appears in the UI. Finally, test real-time behaviour by having another client send messages to you and confirming that they arrive without requiring a manual refresh.

During development, `DialogTube` with `DialogType.Warning` is a convenient way to emit diagnostic information to the screen without a debugger attached. Do not leave these in your final release build.

## Plugin Capabilities and Limitations

Your plugin has full access to the standard .NET Framework class libraries. You can make HTTP requests, open WebSocket connections, parse JSON and XML, read and write files, use SQLite or other embedded databases, load native libraries, and spawn background threads or tasks. There are no sandbox restrictions.

Yggdrasil includes two optional networking helpers described in more detail in the Networking Helpers section below. These are particularly useful if you need to support Windows Vista or Windows 7, where the built-in `SslStream` does not support modern TLS versions. The helpers bypass Windows Schannel entirely and implement TLS through Bouncy Castle, giving you TLS 1.3 and modern cipher suites on any supported version of Windows.

If your plugin needs to load native unmanaged DLLs, use `LibraryHelper.ImportDllFromArchedFolder` from `Yggdrasil.Tools.Windows` to load the right binary for the current process architecture automatically.

There are a few things your plugin cannot do. It cannot modify the Skymu UI beyond what the interface system provides. This is not Firefox: plugins cannot inject custom windows, add toolbar buttons, or change the layout of existing screens. The only UI extension point available is `IExtras`, which adds entries to a dedicated extras menu. Your plugin also cannot communicate with other plugins. Each plugin is loaded in isolation and has no awareness of what other plugins exist.

Because the plugin runs in the same process as Skymu, a blocking operation in your plugin will block the entire application. All interface methods are asynchronous for exactly this reason. Never use `.Result` or `.Wait()` on a Task inside an interface method, and never call `Thread.Sleep` on the calling thread. Use `await` and `Task.Delay` instead.

## Best Practices

### Error Handling

Wrap calls to your protocol's API in a try-catch block. Network code fails in unpredictable ways and you should never let an unhandled exception propagate out of an interface method. When something goes wrong that the user needs to know about, fire `DialogTube` with a `DialogBottle` of type `DialogType.Error` for failures that prevent functionality from working, or `DialogType.Warning` for issues that are noteworthy but not fatal. Always return `false` or the appropriate failure enum value from the method itself so Skymu knows the operation did not succeed.

If a failure produces a long technical message that might help with debugging, use the `copyToClipboardText` overload of `DialogBottle` to put the raw error in the clipboard button while showing a cleaner message in the dialog body. This keeps the UI friendly while still giving technically inclined users access to the details.

### Asynchronous Programming

Every interface method that does any kind of I/O must be implemented as a proper async method. Declare it with `async Task` or `async Task<T>` and await every asynchronous call inside it. If you are calling a synchronous library from within an async method and the call is expensive or blocking, wrap it in `Task.Run` to push it onto the thread pool so it does not block the UI thread. Be careful about shared mutable state when doing this, since your code will then be running on multiple threads.

For background loops such as a WebSocket receive loop, use a `CancellationToken` tied to a `CancellationTokenSource` that you cancel in `Dispose()`. This gives you a clean shutdown path. Never start a background loop without a way to stop it.

### Data Management

The `TypingUsersList` property is an `ObservableCollection<User>` that Skymu binds to directly. Updates to it must happen on the UI thread. If your WebSocket receive loop runs on a background thread, which it will, capture a `SynchronizationContext` during `GetUserInfo()` and use it to post updates to `TypingUsersList`. This is the same pattern used in the stub plugin included in the Skymu source.

Profile pictures are byte arrays, and downloading them can be slow. Cache them locally rather than re-fetching every time a contact or conversation is displayed. A simple in-memory dictionary keyed by user identifier is usually sufficient during a session. For persistent caching across sessions, consider writing to a local folder.

When populating large lists, consider whether you can load a reasonable subset first and stream in the rest. The initial fetch does not need to be exhaustive.

### Authentication and Security

Do not store raw passwords. If your protocol issues session tokens after login, store the token in `SavedCredential` rather than the original password. The `PasswordOrToken` field is a single string, so if you need to persist multiple values (for example a token and a refresh token), encode them together as JSON or a similar format and decode them in `Authenticate(SavedCredential)`.

Validate all data you receive from the protocol API. Servers can return null fields, malformed identifiers, missing keys, or unexpected types, especially for older messages or edge cases like deleted users. Check for null before accessing nested properties and handle malformed data gracefully rather than letting it throw an exception.

### Code Organisation

The main plugin class should be a coordinator, not a monolith. Pull API communication into a separate class or set of classes. Pull WebSocket handling into its own class with its own lifecycle. Pull message mapping (converting API response objects into Yggdrasil models) into a dedicated mapper. This makes the code easier to read, easier to test, and easier to maintain when the protocol API changes.

Document the unusual parts. If the API has a quirk, a race condition, an undocumented field, or a behaviour that contradicts the official documentation, write a comment explaining it. These notes will save significant time later.

## Example Plugin

The following is a minimal but structurally complete skeleton implementing `ICore`. It compiles and satisfies the interface contract, with stub implementations for every required member.

```csharp
using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.Threading.Tasks;
using Yggdrasil;
using Yggdrasil.Models;
using Yggdrasil.Bottles;
using Yggdrasil.Enumerations;

namespace MyPlugin
{
    public class MyProtocol : ICore
    {
        // Tubes
        public event EventHandler<DialogBottle> DialogTube;
        public event EventHandler<MessageBottle> MessageTube;
        public event EventHandler<ListBottle> ListTube;

        // Identity
        public string Name => "My Protocol";
        public string InternalName => "my-protocol-plugin";
        public bool SupportsServers => false;
        public int TypingTimeout => 5000;
        public int TypingRepeat => 9000;

        public AuthTypeInfo[] AuthenticationTypes => new[]
        {
            new AuthTypeInfo(AuthenticationMethod.Password, "Email")
        };

        public ClickableConfiguration[] ClickableConfigurations => new ClickableConfiguration[0];
        public ObservableCollection<User> TypingUsersList { get; } = new ObservableCollection<User>();

        private User _me;

        // Authentication
        public async Task<LoginResult> Authenticate(AuthenticationMethod authType, string username, string password)
        {
            try
            {
                // Your login logic here. On success, store whatever you need to
                // identify the logged-in user and return Success.
                _me = new User("Display Name", username, "user-id-from-api");
                return LoginResult.Success;
            }
            catch (Exception ex)
            {
                DialogTube?.Invoke(this, new DialogBottle(DialogType.Error, "Login failed.", ex.ToString()));
                return LoginResult.Failure;
            }
        }

        public async Task<LoginResult> Authenticate(SavedCredential credential)
        {
            // Restore the session from a previously stored token.
            // Return Failure if the token has expired so Skymu prompts a fresh login.
            _me = credential.User;
            return LoginResult.Success;
        }

        public async Task<LoginResult> AuthenticateTwoFA(string code)
        {
            // Called after Authenticate returns TwoFARequired.
            // Validate the code and return Success or Failure.
            return LoginResult.Success;
        }

        public async Task<SavedCredential> StoreCredential()
        {
            // Called after a successful login. Return the token or credentials
            // needed to restore the session without asking the user to log in again.
            return new SavedCredential(_me, "session-token-from-api", AuthenticationMethod.Token, InternalName);
        }

        public async Task<string> GetQRCode() => string.Empty;

        // User info
        public async Task<User> GetUserInfo()
        {
            // Return a User representing the logged-in account.
            // Capture SynchronizationContext.Current here if you need it later.
            return _me;
        }

        // Lists
        public async Task<List<DirectMessage>> FetchContacts()
        {
            var contacts = new List<DirectMessage>();
            // Fetch from your API and populate the list.
            return contacts;
        }

        public async Task<List<Conversation>> FetchConversations()
        {
            var conversations = new List<Conversation>();
            // Fetch from your API and populate the list.
            return conversations;
        }

        public async Task<List<Server>> FetchServers()
        {
            // SupportsServers is false, so this will not be called.
            // Return an empty list as a safe stub.
            return new List<Server>();
        }

        // Messages
        public async Task<List<ConversationItem>> FetchMessages(
            Conversation conversation,
            Fetch fetchType = Fetch.Newest,
            int messageCount = 50,
            string identifier = null)
        {
            var messages = new List<ConversationItem>();
            // Use fetchType and identifier to determine which page of messages
            // to request from your API, then map the results to Message objects.
            return messages;
        }

        public async Task<bool> SendMessage(
            string conversationId,
            string text = null,
            Attachment attachment = null,
            string parentMessageId = null,
            bool action = false)
        {
            try
            {
                // Send the message to your API. Once confirmed, fire MessageTube
                // so Skymu adds the message to the conversation view.
                var sent = action
                    ? (ConversationItem)new ActionMessage("new-msg-id", _me, DateTime.UtcNow, text)
                    : new Message("new-msg-id", _me, DateTime.UtcNow, text);

                MessageTube?.Invoke(this, new MessageRecievedBottle(conversationId, sent, false));
                return true;
            }
            catch (Exception ex)
            {
                DialogTube?.Invoke(this, new DialogBottle(DialogType.Error, "Failed to send message.", ex.ToString()));
                return false;
            }
        }

        public async Task<bool> EditMessage(string conversationId, string messageId, string newText)
        {
            // Implement if your protocol supports message editing.
            return false;
        }

        public async Task<bool> DeleteMessage(string conversationId, string messageId)
        {
            // Implement if your protocol supports message deletion.
            return false;
        }

        // Presence
        public async Task<bool> SetConnectionStatus(PresenceStatus status) => true;
        public async Task<bool> SetMood(string status) => true;
        public async Task<bool> SetTyping(string identifier, bool typing) => false;

        public void Dispose()
        {
            // Cancel background tasks, close WebSocket connections,
            // stop timers, and release any other resources held by this plugin.
        }
    }
}
```

For a full working example with realistic data, calls, extras, and real-time updates wired up through every tube, see the stub plugin in the Skymu source. It implements all four interfaces and is a good reference for how the pieces fit together in practice.

## Enumerations

### AuthenticationMethod

This enum defines the authentication methods a plugin can support and is used in both `AuthTypeInfo` and `SavedCredential`.

`Password` is standard username and password login. `QRCode` is QR code based authentication, which requires `GetQRCode()` to return a valid scannable string. `Passwordless` covers flows such as magic link emails where no password is involved. `External` is for external authentication providers. `Token` is for token-based login, which is commonly used in `SavedCredential` to represent a stored session even if the original login used a different method.

### LoginResult

`Success` means the login completed and Skymu will proceed to call `GetUserInfo()`. `TwoFARequired` means the first factor was accepted and Skymu should prompt for a second factor and call `AuthenticateTwoFA()`. `Failure` means the login was rejected. `UnsupportedAuthType` means the requested authentication method is not supported by this plugin.

### PresenceStatus

Covers the standard range of user presence states: `Online`, `Away`, `DoNotDisturb`, `Invisible`, `Offline`, and `Unknown`. Mobile variants are also available for protocols that distinguish between desktop and mobile clients: `OnlineMobile`, `AwayMobile`, and `DoNotDisturbMobile`. `Blocked` can be used to represent a contact the user has blocked.

### ClickableItemType

Used in `ClickableConfiguration` to declare what kind of entity a clickable span in a message refers to. `User` is a user mention. `Server` is a server reference. `ServerRole` is a role mention. `ServerChannel` is a channel reference. `GroupChat` is a group conversation reference.

### ChannelType

Used in `ServerChannel` to describe the nature of a channel. `Standard` is a normal read/write channel. `ReadOnly` is a channel where members can read but not post. `Announcement` is a channel where only authorised users can post and others can only read. `Voice` is an audio or video channel. `Restricted` is a channel with limited access that the user can partially see. `NoAccess` is a channel that exists in the server structure but that the user has no access to at all. `Forum` is a forum-style channel where conversations are organised as threads.

### Fetch

Controls the direction and anchor point of a `FetchMessages` call. `Newest` loads the most recent messages in the conversation. `Oldest` loads from the very beginning of the conversation history. `BeforeIdentifier` loads messages older than the message specified by the `identifier` parameter, used for backwards pagination. `AfterIdentifier` loads messages newer than the specified message, used for forwards pagination. `NewestAfterIdentifier` loads the most recent messages but only those that come after the specified message, which is useful for fetching new messages after a known point without loading the full history.

### DialogType

`Error` is for failures that prevent something from working and should be used sparingly so it retains impact. `Warning` is for issues that are noteworthy but do not block the user. `Information` is for neutral messages you want to surface to the user. `Question` is for yes/no dialogs and requires the `Action` overload of `DialogBottle` so the user's choice can be delivered back to your plugin.

### ListType

Used in `ListBottle` subtypes to identify which list is being updated. `Contacts` refers to the contacts list. `Conversations` refers to the conversations and recents list. `Servers` refers to the server list.

### CallState

`Ringing` means the call has been initiated and is awaiting an answer. `Active` means the call is connected. `Ended` means the call concluded normally. `Failed` means the call could not be established or was interrupted abnormally.

### AttachmentType

Describes the type of an `Attachment` object. `Image` is a full-resolution image. `ThumbnailImage` is a lower-resolution preview image. `Video` is a video file. `Audio` is an audio file. `File` is a generic binary file of any other type.

## Data Classes

### Metadata

`Metadata` is the abstract base class for most identifiable objects in Yggdrasil, including `User`, `Conversation`, and `Server`. It provides three properties: `Identifier`, which is a stable unique ID string from the protocol; `DisplayName`, which is the human-readable name shown in the UI; and `ProfilePicture`, which is a raw image byte array. All three properties are backed by `INotifyPropertyChanged`, so any changes you make to them on live objects will automatically propagate to the UI through data bindings.

### User

`User` extends `Metadata` and adds `Username`, `PublicUsername`, `Status`, and `ConnectionStatus`. `Username` is the account's login name or handle. `PublicUsername` is the name shown in public contexts such as server member lists; it falls back to `Username` if not explicitly set, which is appropriate for most protocols. `Status` is the user's text mood string. `ConnectionStatus` is a `PresenceStatus` value representing their current presence.

**Constructor:** `User(string displayName, string username, string identifier, string status = null, PresenceStatus presenceStatus = PresenceStatus.Offline, byte[] profilePicture = null)`

### Conversation

`Conversation` is the abstract base class for all conversation types. It extends `Metadata` and adds `UnreadCount` and `LastMessageTime`.

`DirectMessage` represents a one-on-one conversation. It is constructed with the other participant as a `User` object, the unread count, a conversation identifier, and an optional last message time. The display name and profile picture of the conversation are taken directly from the partner `User`.

`Group` represents a multi-person group chat. It is constructed with a group name, an identifier, an unread count, a `User[]` members array, an optional profile picture, and an optional last message time.

### Server

`Server` extends `Metadata` and represents a community with multiple channels. It holds a `User[]` of members, a `ServerChannel[]` of channels, an `ObservableCollection<object>` called `GroupedChannels` for displaying channels under category headers in the UI, a `MemberCount`, and a `CategoryMap` which is a `Dictionary<string, string>` mapping category IDs to display names.

**Constructor:** `Server(string name, string identifier, User[] members, ServerChannel[] channels, byte[] profilePicture = null, Dictionary<string, string> categoryMap = null, int memberCount = 0)`

### ServerChannel

`ServerChannel` extends `Conversation` and adds `ParentServerID`, `Description`, `ChannelType`, `CategoryID`, and `Position`. Set `CategoryID` to match a key in the parent server's `CategoryMap` if the channel belongs to a category. `Position` is used to order channels within a category.

**Constructor:** `ServerChannel(string name, string identifier, string parentServerId, int unreadCount, ChannelType channelType, string categoryId = null, int position = 0, string description = null, DateTime? lastMessageTime = null)`

### ConversationItem

`ConversationItem` is the abstract base class for all items that can appear in a conversation view. It has three properties: `Time` (when the item occurred), `Identifier` (a unique ID for this item within the protocol), and `ConversationId` (the identifier of the conversation it belongs to).

### Message

`Message` extends `ConversationItem` and represents a standard chat message. The `Author` property is a `User` object. `Text` is the message body string, which may be null if the message is attachment-only. `Attachments` is an optional `Attachment[]` array. `ParentMessage` is an optional `Message` reference for reply threading. `IsForwarded` indicates that the message was forwarded from another conversation.

**Constructor:** `Message(string identifier, User author, DateTime time, string text = null, Attachment[] attachments = null, Message parentMessage = null, bool isForwarded = false)`

### ActionMessage

`ActionMessage` extends `Message` and represents an action or emote-style message, such as those produced by `/me` commands. It has the same constructor as `Message`. Skymu displays action messages differently from standard messages.

### CallStartedNotice

`CallStartedNotice` extends `ConversationItem` and represents the notification that appears in a conversation when a call is started. It carries a `User StartedBy` and a `bool IsVideoCall`.

**Constructor:** `CallStartedNotice(User startedBy, bool isVideoCall, DateTime time)`

### CallEndedNotice

`CallEndedNotice` extends `ConversationItem` and represents the notification that appears when a call ends. It carries a `User StartedBy`, a `TimeSpan Duration`, and a `bool IsVideoCall`. Note that the `time` parameter is when the call ended, not when it started.

**Constructor:** `CallEndedNotice(User startedBy, TimeSpan duration, bool isVideoCall, DateTime time)`

### Attachment

`Attachment` represents a file or media attachment on a message. It has three constructors depending on how the attachment is sourced.

Use `Attachment(byte[] file, string name)` for a simple file upload where you have the raw bytes. Use `Attachment(byte[] file, string name, string url, AttachmentType type)` for an attachment that has both local bytes and a remote URL, with an explicit type. Use `Attachment(string locationUrl, string name)` for an attachment that is only available by URL, which is the common case for attachments on messages fetched from the server.

### AuthTypeInfo

`AuthTypeInfo` describes a single authentication method that your plugin supports. Pass an `AuthenticationMethod` value and optional custom strings to override the default labels in the login UI.

**Constructor:** `AuthTypeInfo(AuthenticationMethod type, string customTextUsernameField = null, string customTextAuthType = null)`

### SavedCredential

`SavedCredential` holds everything Skymu needs to restore a session without prompting the user to log in again. It contains the `User` object of the logged-in account, a `PasswordOrToken` string, the `AuthenticationMethod` that was used, and the `InternalName` of the plugin that created it.

**Constructor:** `SavedCredential(User user, string passwordOrToken, AuthenticationMethod authenticationType, string plugin)`

### ClickableConfiguration

`ClickableConfiguration` describes a pattern for recognising a clickable span in message text. When Skymu renders a message, it scans for these patterns and makes the matching spans tappable, routing them to the appropriate action for the item type.

`DelimiterLeft` is the opening delimiter string, such as `"<@"` or `"@"`. `DelimiterRight` is the closing delimiter string, such as `">"`. If the item is delimited only on the left, for example a bare username mention that ends at the next space, set `DelimiterRight` to a space. `Type` is the `ClickableItemType` that tells Skymu what the matched span refers to.

**Constructor:** `ClickableConfiguration(ClickableItemType type, string delimiterLeft, string delimiterRight)`

### ActiveCall

`ActiveCall` represents a call that is currently in progress. It is returned by `StartCall` and `AnswerCall` and passed back to `EndCall`, `SetMuted`, and `SetVideoEnabled`. It carries a `CallId`, a `ConversationId`, an `IsVideo` flag, a `StartedAt` timestamp, a `User[]` of participants, and a mutable `State` property of type `CallState`.

**Constructor:** `ActiveCall(string callId, string conversationId, bool isVideo, User[] participants)`

### ExtraConfiguration

`ExtraConfiguration` represents a single entry in Skymu's extras menu. It has a `title`, an optional `description`, and an `onRun` action that is invoked when the user selects it.

**Constructor:** `ExtraConfiguration(string title, Action onRun, string description = null)`

## Bottles

### DialogBottle

`DialogBottle` is loaded into `DialogTube` when your plugin wants to show a dialog to the user. It has three constructor overloads.

`DialogBottle(DialogType type, string message)` shows a simple dialog with a message and a dismiss button. `DialogBottle(DialogType type, string message, string copyToClipboardText)` shows a dialog that also has a button to copy text to the clipboard, which is useful for surfacing raw error messages or tokens without cluttering the dialog body. `DialogBottle(DialogType type, string message, Func<bool, object> action)` shows a yes/no dialog and delivers the user's response through the callback, where `true` means the user confirmed and `false` means they cancelled.

### MessageBottle

`MessageBottle` is the abstract base class for all message-related event arguments. It carries a `ConversationId` identifying which conversation the event concerns. Use one of three concrete subtypes when invoking `MessageTube`.

`MessageRecievedBottle` is used when a new message arrives. Its constructor takes the conversation ID, the `ConversationItem` that was received, and a boolean indicating whether the message was sent in a server channel rather than a direct or group conversation.

`MessageEditedBottle` is used when an existing message is edited. Its constructor takes the conversation ID, the identifier of the old message, and the new `ConversationItem` that replaces it.

`MessageDeletedBottle` is used when a message is deleted. Its constructor takes the conversation ID and the identifier of the deleted message.

### ListBottle

`ListBottle` is the abstract base class for list-related event arguments. It carries a `ListType` value identifying which list was affected. Use one of two concrete subtypes when invoking `ListTube`.

`ListItemUpdatedBottle` is used when a contact, conversation, or server is added or its details change. Its constructor takes the `ListType` and a `Metadata` object representing the item. Skymu will add the item to the list if it is not already present, or update it in place if it is.

`ListItemRemovedBottle` is used when a contact, conversation, or server should be removed from a list. Its constructor takes the `ListType` and the identifier string of the item to remove.

### CallBottle

`CallBottle` is used with both `IncomingCallTube` and `CallStateChangedTube`. It carries a `ConversationId`, a `CallState`, and optionally a `FailReason` string or a `User` representing the caller.

Use `CallBottle(string convoId, CallState state, User caller)` when firing `IncomingCallTube`, so Skymu can display the caller's name and picture in the incoming call UI. Use `CallBottle(string convoId, CallState state)` or `CallBottle(string convoId, CallState state, string failReason)` when firing `CallStateChangedTube` to report that the call state has changed, optionally with a reason string if the state is `Failed`.

## ICore Reference

`ICore` is required for all plugins. The following is a complete listing of its members.

**Tubes:** `event EventHandler<DialogBottle> DialogTube`, `event EventHandler<MessageBottle> MessageTube`, `event EventHandler<ListBottle> ListTube`.

**Properties:** `string Name`, `string InternalName`, `AuthTypeInfo[] AuthenticationTypes`, `bool SupportsServers`, `int TypingTimeout`, `int TypingRepeat`, `ClickableConfiguration[] ClickableConfigurations`, `ObservableCollection<User> TypingUsersList`.

**Methods:** `Task<SavedCredential> StoreCredential()`, `Task<string> GetQRCode()`, `Task<LoginResult> Authenticate(AuthenticationMethod authType, string username, string password)`, `Task<LoginResult> Authenticate(SavedCredential credential)`, `Task<LoginResult> AuthenticateTwoFA(string code)`, `Task<bool> SendMessage(string conversationId, string text, Attachment attachment, string parentMessageId, bool action)`, `Task<bool> EditMessage(string conversationId, string messageId, string newText)`, `Task<bool> DeleteMessage(string conversationId, string messageId)`, `Task<User> GetUserInfo()`, `Task<List<DirectMessage>> FetchContacts()`, `Task<List<Conversation>> FetchConversations()`, `Task<List<Server>> FetchServers()`, `Task<List<ConversationItem>> FetchMessages(Conversation conversation, Fetch fetchType, int messageCount, string identifier)`, `Task<bool> SetConnectionStatus(PresenceStatus status)`, `Task<bool> SetMood(string status)`, `Task<bool> SetTyping(string identifier, bool typing)`, `void Dispose()`.

## ICall Reference

`ICall` is optional. Implement it if your protocol supports voice or video calls.

**Tubes:** `event EventHandler<CallBottle> IncomingCallTube`, `event EventHandler<CallBottle> CallStateChangedTube`.

**Properties:** `bool SupportsVideoCalls`.

**Methods:** `Task<ActiveCall> StartCall(string convoId, bool isVideoCall, bool startMuted)`, `Task<ActiveCall> AnswerCall(string convoId)`, `Task<bool> DeclineCall(string convoId)`, `Task<bool> EndCall(ActiveCall call)`, `Task<bool> SetMuted(ActiveCall call, bool muted)`, `Task<bool> SetVideoEnabled(ActiveCall call, bool enabled)`.

`StartCall` is called when the user initiates an outgoing call. Return an `ActiveCall` on success, or `null` if the call could not be started. `AnswerCall` is called when the user accepts an incoming call that arrived through `IncomingCallTube`. `DeclineCall` is called when the user rejects an incoming call. `EndCall` is called when the user hangs up. `SetMuted` and `SetVideoEnabled` are called when the user toggles their microphone or camera, and are used to signal this state to the remote party through the protocol if the protocol supports it.

## IListManagement Reference

`IListManagement` is optional. Implement it if your protocol supports discovering and adding new contacts or conversations.

**Methods:** `Task<Metadata[]> FindNewContact(string query)`, `Task<bool> AddContact(Metadata metadata, string message)`.

`FindNewContact` is the first step in the add contact flow. It receives a search query string and returns an array of `Metadata` objects representing the results. These can be `User` objects for individual contacts or `Group` objects for group conversations the user could join. If your protocol does not support searching, return a single dummy result such as a `User` with display name "Add me!" so the UI has something to show. `AddContact` is the second step, called when the user selects one of the results. The `message` parameter contains an optional introductory message if the UI collected one. Fire `ListTube` with a `ListItemUpdatedBottle` to add the new contact or conversation to the appropriate list, and return `true` on success.

## IExtras Reference

`IExtras` is optional. Implement it to add entries to Skymu's extras menu.

**Properties:** `ObservableCollection<ExtraConfiguration> ExtraConfigurations`.

Each `ExtraConfiguration` in the collection has a `title` displayed in the menu, an optional `description` shown as a subtitle, and an `onRun` action invoked when the user selects it. You can update `ExtraConfigurations` at runtime and the menu will reflect the changes.

## Networking Helpers

### BifrostEngine

`BifrostEngine` is a custom `HttpMessageHandler` that can be passed to `HttpClient` as a drop-in replacement for `HttpClientHandler`. It establishes TLS connections using Bouncy Castle rather than Windows Schannel, which means it supports TLS 1.3 and modern cipher suites on Windows Vista and Windows 7, where `SslStream` does not.

```csharp
var client = new HttpClient(new BifrostEngine());
var response = await client.GetAsync("https://api.example.com/endpoint");
```

It supports keep-alive connection pooling with a configurable pool size, automatic redirect following up to a configurable maximum, gzip and deflate decompression, and chunked transfer encoding. If you are not targeting legacy Windows versions and `HttpClientHandler` works for your protocol, you do not need to use `BifrostEngine`, but it is available if you do.

### BifrostWebSocket

`BifrostWebSocket` is a WebSocket client that mirrors the interface of .NET's `ClientWebSocket` but uses Bouncy Castle for TLS in the same way as `BifrostEngine`. This makes it suitable for maintaining a WebSocket gateway connection on Windows Vista and Windows 7.

```csharp
var ws = new BifrostWebSocket();
ws.Options.SetRequestHeader("Authorization", "Bearer " + token);
ws.Options.AddSubProtocol("json");
await ws.ConnectAsync(new Uri("wss://gateway.example.com/v1"), cancellationToken);

// Receiving
var buffer = new byte[4096];
var result = await ws.ReceiveAsync(new ArraySegment<byte>(buffer), cancellationToken);
var text = Encoding.UTF8.GetString(buffer, 0, result.Count);

// Sending
var bytes = Encoding.UTF8.GetBytes(payload);
await ws.SendAsync(new ArraySegment<byte>(bytes), WebSocketMessageType.Text, true, cancellationToken);
```

It handles ping/pong frames automatically, supports subprotocol negotiation, and correctly recovers bytes that were buffered during the HTTP upgrade handshake so no data is lost between the upgrade response and the first WebSocket frame.

### LibraryHelper

`LibraryHelper.ImportDllFromArchedFolder` loads an unmanaged native DLL from an architecture-specific subfolder relative to your plugin assembly. If your plugin depends on a native library such as a media codec or a cryptographic implementation, place the binaries in subfolders named `x86`, `x64`, `arm32`, and `arm64` alongside your plugin DLL, then call this method with the filename at startup.

```csharp
LibraryHelper.ImportDllFromArchedFolder("libsodium.dll");
// Loads x64\libsodium.dll on a 64-bit x86 process,
// x86\libsodium.dll on a 32-bit x86 process, and so on.
```

The method throws `PlatformNotSupportedException` if the current process architecture is not one of the four supported values, and `DllNotFoundException` if the DLL file does not exist under the expected path for the detected architecture.