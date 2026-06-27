# Privacy Policy 

Skymu is an open-source messaging client which connects to Skymu-owned servers during operation. This policy describes what data is handled and where. (Last updated: April 19, 2026)

## What we collect

When Skymu is running and you are signed in, the following is transiently held in memory on our presence server to power the user directory feature:

- Display name
- Username
- Active plugin (e.g. "Discord", "Matrix")
- Skymu build version and codename
- Last ping timestamp
- IP address (to prevent service abuse)

This data is not written to disk or a database. It exists only in server memory for the duration of your session and is discarded when you go offline, your session expires (~70 seconds after last ping), or the server restarts. In addition, you can disable communication with any Skymu servers in the client settings.

## What we do not collect
- Passwords or credentials of any kind
- Message content
- Contact lists or friend lists
- Any persistent user profiles or accounts

## Local storage

Credentials (tokens, passwords, authentication data) for third-party services are stored locally on your device only, protected by Windows DPAPI. Skymu's developers have no access to this data.

Settings and configuration are also stored locally and do not leave your machine.

## Third-party services

Skymu connects to third-party services (Discord, Matrix, XMPP, Microsoft Teams, etc.) directly on your behalf using credentials you provide. These connections are made from your device to the respective service. Skymu's servers are not involved in relaying your messages or credentials. These services most likely do retain your data and you will need to consult the service in question for information on how they handle it.

## Open source

Skymu's source code is publicly available. You can independently verify what data is sent where.

## Changes

If this policy changes materially, we will update the date above and note it in the changelog.