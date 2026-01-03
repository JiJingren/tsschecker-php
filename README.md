# tsschecker-php
A PHP implementation of tsschecker for saving iOS SHSH blobs.

| Property | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| **device** | String | Yes | Device identifier (e.g., `iPhone10,6`). |
| **ecid** | String / Int | Yes | Device ECID; supports `0x` prefix (Hex) or Decimal format. |
| **iosVersion** | String | Optional | iOS version number (required for online fetching mode). |
| **buildVersion** | String | Optional | Specific Build ID; can be used instead of version (e.g., `21E219`). |
| **savePath** | String | No | Local directory path where the SHSH result will be saved. |
| **quiet** | Boolean | No | Enables quiet mode. If `false`, detailed execution logs are printed. |
| **generator** | Int | No | Custom Generator (Nonce); a random one is generated if left empty. |
| **manifestPath** | String | No | Path to a local `BuildManifest.plist` to skip remote downloading. |
