# Privacy Policy 

**Version 2, 06/29/2026**

A lot of services have long privacy policies, usually because they handle and collect a lot of data. Skymu is a privacy-conscious application that collects very little data so this is going to be relatively short. We want to be completely transparent about the data we collect so this will be a simple document as well. 

# Data collected by Skymu

## With default settings

### What 

- The last time your Skymu installation contacted the user server
- The IP address and user agent of the client connecting
- The plugin you are using (e.g. "Discord", "Matrix")
- The build name of your Skymu installation (e.g. "Elder Guardian")
- The build version of your Skymu installation (e.g. "0.4.6")

### Why 

- The first one is necessary for the user count feature to function.
- The second one is really not information we want to collect, but we need a way to block spammers and malicious actors from our services. We don't perform any IP lookups or do anything with this information unless you are DDoSing or otherwise abusing the service.
- The other three are so that we know how many users are using certain plugins or versions of Skymu so we know whether to keep maintaining them or not. 
- Except the IP address, all this data is publicly available at https://skymu.app/users

## If you have opted to share user info

### What 

- Everything in the *With default settings* section
- User information in a User() object, as reported by the plugin you are using. This includes your display name, username, and profile picture.

### Why 

- To populate the user directory so other people can search a list of active Skymu users, functionality that was available in Skype.
- We do not store or retain this information. It is purged the minute you close Skymu.
- You would have specifically chosen (opted in) to provide this data. Anonymizing user info is enabled by default to protect user privacy.
- This data is also publicly available at https://skymu.app/users

## If you have opted to block communication

### What

- Absolutely nothing. No data is ever transferred to us and your Skymu installation never contacts our servers.
- However, certain features of Skymu like the user count and user directory will not work for you.

### Why

- We find it important to offer this option.

## While using the website

### What 

- Your IP address
- Your user agent
- The page you are visiting
- Your referrer, if applicable

### Why

- This is standard security practice even among other open-source hobbyist services. It's really not information we want to collect, but we need a way to block spammers and malicious actors from our services. We don't perform any IP lookups or do anything with this information unless you are DDoSing or otherwise abusing the service.

# Data collected by others

## Official plugins

Skymu plugins do not communicate with Skymu servers at all or collect any data on our side. However, these plugins connect to third-party services (Discord, Matrix, Chat Completions servers, Microsoft Teams, etc.) directly on your behalf using credentials you provide. These connections are made from your device to the respective service. Skymu's servers are not involved in relaying your messages or credentials and we do not monitor, handle, or collect any of this data. These services most likely do retain your data and you will need to consult the service in question for information on how they handle it.

## Third-party plugins

As Skymu plugins can be made by anyone, a malicious third-party plugin (a plugin made by someone other than the Skymu developers) can potentially collect extensive telemetric data as well as sensitive information on your computer and relay it to its own servers. Since we are not the developers of those plugins, we do not hold any responsibility for anything they do, including data collection. No third-party plugins are included with Skymu and you are fully responsible for anything you download or install from the internet.

# Changes

If the content of this policy changes, we will inform you.