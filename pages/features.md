# Skymu Features  
Skymu is a multiplatform recreation of classic Skype, bringing the nostalgic Skype user interface into the modern era with support for multiple different messaging services. Here is an overview of what Skymu can do. (Last revision: June 18, 2026)
  
## Messaging  
- Send and receive messages across supported plugins  
- Message sending animation while a message is being sent  
- Deleted messages properly delete in real time  
- Markdown rendering via Markdig, including proper formatting support  
- Shortcode emoji support (e.g. `:smile:`)  
- Phishing link detection: masked Markdown links that redirect to a different destination than displayed will show a warning  
- Image attachments cached locally to a Media folder, with magic-byte extension sniffing for correct file types  
- Reply/quote support via parent message references  
- `/me` action message support  
- File sending support with localized send file prompt  
  
## Servers  
- Full server support, including channels organized under a server  
- Collapsible channels  
- Channel type icons with finalized icon colors: light blue for text channels, dark blue for announcements and forums, dark green handset for voice channels  
  
## Calls  
- Voice and video call support via the ICall plugin interface  
- Incoming call notifications with accept/decline  
- Mute and video toggle during calls, signalled to the remote party where the platform supports it  
- Call started and call ended notices shown in conversation history, with duration  
- Call sounds  
- Opt-in accuracy setting: unreleased 'room-style' call UI from the mid-2012 Skype 5.5 mockups  
- Opt-in setting to use the call reconnecting sound instead of the normal call out sound  
  
## Status  
- Set your Skype status from within the app by clicking the status icon  
- Context menu to set Skype status  
  
## Login and Credentials  
- Discord QR code login (no token required)  
- Credential storage using the Windows Credential Manager, the modern and secure approach since Windows Vista  
- Optional credential storage to file (credentials.xml) as an alternative  
- Optional automatic login when credentials are saved  
- Built with multiple saved sessions per plugin and switchable user accounts in mind  
  
## Notifications  
- Notifications support, made fully accurate to classic Skype behavior  
- Opt-in setting to force active notifications to use blue instead of the accurate orange  
  
## Accuracy and Appearance  
- Skype 4, 5, 6, and 7 era UI themes, selectable from settings  
- Light and dark theme support: a dark color mode applies a pixel shader effect to all themed UI assets, with full support across all four Skype eras  
- Color theme system: themes are loaded from Themes/*.xaml at startup with live switching and automatic fallback to Default  
- Complete window border support  
- Skype Sounds  
- Dynamic sidebar tabs: an opt-in accuracy setting matching Skype 5.5.x through 5.10.x behavior, where the selected tab takes up most of the sidebar width and unselected tabs are fixed at 32px. Disabled by default.  
- Buttons automatically size to text, which also helps with alternative language support  
- Menu Bar items accurately sized  
- Opt-in setting to use the 'Classic Windows' Skype main color instead of dynamic colors  
- Remember window positioning (window size, location, sidebar size) across sessions  
- Conversation list selection triggers on mouse button release for accurate Skype behavior  
  
## Database  
- Full Skype-compatible SQLite database (main.db) stored per-account and per-plugin under AppData  
- Schema covers Accounts, Contacts, Conversations, Participants, Messages, and Transfers tables, with over 90% column compatibility with original Skype databases for use with legacy Skype tooling  
- Versioned database with automatic wipe-and-recreate on schema change, with a user-facing prompt when a newer database version is encountered  
- Upsert-based writes for contacts, conversations, participants, and messages, safe to call repeatedly without duplicates  
- Image attachments stored to disk and tracked in the Transfers table, recoverable across sessions  
- Call history stored as typed message rows (call started/ended) compatible with Skype database readers  
- MessageLogger: opt-in setting to retain original versions of edited and deleted messages  
  
## Networking (Bifrost)  
- Custom TLS layer built on BouncyCastle, bypassing Windows SChannel for compatibility with Vista and Windows 7  
- Three certificate store modes: Embedded (built-in cacert.pem bundle), System (Windows certificate store), and Custom (user-supplied PEM file), configurable via ratatoskr.xml  
- Full certificate chain walking with signature verification, expiry checks, and depth limiting  
- SAN (Subject Alternative Name) hostname verification with wildcard support; optional CN fallback for older certificates  
- SNI (Server Name Indication) always sent  
- Dual-stack IPv4/IPv6 socket support  
  
## Plugins  
Skymu is built on a plugin system using MiddleMan interfaces. Plugins are written against one or more of the following interfaces:  
- **ICore**: required for all plugins, covers authentication, messaging, contacts, conversations, and presence  
- **ICall**: for plugins that support voice and video calling  
- **IListManagement**: for contact search and add flows  
- **IExtras**: for plugin-specific menu actions exposed through the Extras menu  
  
Supported plugins include:  
- **Discord**: Full support for the Discord platform, including QR code login, real-time messaging via WebSocket gateway, presence/status updates, voice calls, and server/channel browsing  
- **Matrix**: Decentralised, open messaging protocol. Supports Beeper passwordless email OTP authentication and E2EE SAS verification  
- **Tox**: Peer-to-peer, serverless encrypted messaging protocol with no central infrastructure  
- **MSNP11**: Microsoft's classic MSN Messenger protocol, bringing back the original Windows Live Messenger experience  
  
## Skype Home  
- Skype Home page restored, with SH.API COM bridge integration  
- Community ads system with mappings.json  
- Opt-in setting to disable community ads while using Skype Home  
  
## Localization  
- Multiple language support  
- Opt-in setting to follow system regional variances (time format, date format, units)  
- Localized updater and send file dialogs  
  
## Other  
- Auto updater  
- Autorun support  
- Image viewing  
- Group chat support  
- Settings panel, including opt-in accuracy settings  
- XML-based configuration system (shared.xml) with versioning and automatic reset on corruption or format mismatch; Yggdrasil cross-platform config (ratatoskr.xml) for networking settings  
- Allow multiple instances of the application to run simultaneously (opt-in)  
- Start with the window minimized (opt-in)  
- Report a bug / Give feedback button  
- Easter egg: opt-in setting to replace all dialog icons with 'Niko' from the game *Oneshot*  
- Privacy: opt-in anonymization of username and display name in Skymu's connected user statistics  
- Privacy: opt-in setting to completely block all communication with the Skymu server and updater  
  
## Website and Wiki  
- Download and community links available at skymu.app  
- Documentation and wiki available at skymu.app/wiki