<?php
require_once 'src/TssChecker.php';

$checker = new TssChecker();

/**
 * Device Identity Parameters
 */
$checker->device = 'iPhone4,1';         // Product Type (e.g., iPhone4,1)
$checker->ecid = '0x123456789ABC';       // Device ECID (Supports Hex string or Integer)
$checker->boardconfig = null;            // Optional: Specific board config (e.g., n104ap)

/**
 * Firmware Versioning (Choose one or both)
 */
$checker->iosVersion = '9.3.6';           // Target iOS Version
$checker->buildVersion = null;           // Optional: Specific Build ID (e.g., 21E219)

/**
 * Saving & Manifest Options
 */
$checker->savePath = './ios.shsh2';       // Local path to save the SHSH2 blob
$checker->manifestPath = null;            // Optional: Local path to BuildManifest.plist

/**
 * Advanced Security Parameters
 */
$checker->generator = 0x1111111111111111;  // 64-bit unsigned integer Generator
$checker->nonce = null;                    // Optional: Specific Hex Nonce string
$checker->noBaseband = false;              // Set to true to skip baseband signing

/**
 * Execution Mode
 */
$checker->quiet = true;                    // Enable quiet mode to suppress all output

/**
 * Run the Task
 */
$checker->run();                           // Execute the TSS request logic
