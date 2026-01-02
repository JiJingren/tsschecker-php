<?php
require_once 'src/TssChecker.php';

function print_usage() {
    echo "Usage: php tsschecker.php [OPTIONS]\n";
    echo "  -d, --device MODEL       Target device (e.g. iPhone8,1)\n";
    echo "  -B, --boardconfig CODE   Board config (e.g. n71ap)\n";
    echo "  -m, --manifest PATH      Path to BuildManifest.plist\n";
    echo "  -i, --ios VERSION        iOS Version (e.g. 9.3.5) for online mode\n";
    echo "  --build-version BUILD    Build Version (e.g. 13G36) for online mode\n";
    echo "  -e, --ecid ECID          ECID in hex or dec\n";
    echo "  -g, --generator GEN      Generator for nonce (hex)\n";
    echo "  --apnonce NONCE          APNonce (hex)\n";
    echo "  -b, --no-baseband        Don't check baseband\n";
    echo "  -s, --save PATH          Save ticket to file\n";
    echo "  -h, --help               Show this help\n";
}

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$checker = new TssChecker();

$argv = $_SERVER['argv'];
$argc = count($argv);

if ($argc < 2) {
    print_usage();
    exit(1);
}

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    
    if ($arg == '-h' || $arg == '--help') {
        print_usage();
        exit(0);
    } elseif ($arg == '-d' || $arg == '--device') {
        if ($i + 1 < $argc) $checker->device = $argv[++$i];
    } elseif ($arg == '-B' || $arg == '--boardconfig') {
        if ($i + 1 < $argc) $checker->boardconfig = $argv[++$i];
    } elseif ($arg == '-m' || $arg == '--manifest') {
        if ($i + 1 < $argc) $checker->manifestPath = $argv[++$i];
    } elseif ($arg == '-i' || $arg == '--ios') {
        if ($i + 1 < $argc) $checker->iosVersion = $argv[++$i];
    } elseif ($arg == '--build-version') {
        if ($i + 1 < $argc) $checker->buildVersion = $argv[++$i];
    } elseif ($arg == '-e' || $arg == '--ecid') {
        if ($i + 1 < $argc) $checker->ecid = $argv[++$i];
    } elseif ($arg == '-s' || $arg == '--save') {
        if ($i + 1 < $argc) $checker->savePath = $argv[++$i];
    } elseif ($arg == '-g' || $arg == '--generator') {
        if ($i + 1 < $argc) {
            $val = $argv[++$i];
            if (stripos($val, '0x') === 0) $val = hexdec($val);
            else $val = intval($val);
            $checker->generator = $val;
        }
    } elseif ($arg == '--apnonce') {
        if ($i + 1 < $argc) $checker->nonce = $argv[++$i];
    } elseif ($arg == '-b' || $arg == '--no-baseband') {
        $checker->noBaseband = true;
    }
}

$checker->run();
