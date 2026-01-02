<?php

class DeviceDB {
    // 格式：[产品类型, 主板配置, CPID, BDID]
    private static $devices = [
        // iPhone 4s
        ['iPhone4,1', 'n94ap', 0x8940, 0x08],

        // iPhone 5
        ['iPhone5,1', 'n41ap', 0x8950, 0x00],
        ['iPhone5,2', 'n42ap', 0x8950, 0x02],

        // iPhone 5c
        ['iPhone5,3', 'n48ap', 0x8950, 0x08],
        ['iPhone5,4', 'n49ap', 0x8950, 0x0A],

        // iPhone 5s
        ['iPhone6,1', 'n51ap', 0x8960, 0x00],
        ['iPhone6,2', 'n53ap', 0x8960, 0x02],

        // iPhone 6
        ['iPhone7,2', 'n61ap', 0x7000, 0x00],
        ['iPhone7,1', 'n56ap', 0x7000, 0x02], // 6 Plus

        // iPhone 6s
        ['iPhone8,1', 'n71ap', 0x8000, 0x04],
        ['iPhone8,1', 'n71map', 0x8003, 0x04],
        ['iPhone8,2', 'n66ap', 0x8000, 0x06],
        ['iPhone8,2', 'n66map', 0x8003, 0x06],
        ['iPhone8,4', 'n69ap', 0x8000, 0x02], // SE
        ['iPhone8,4', 'n69uap', 0x8003, 0x02], // SE

        // iPhone 7
        ['iPhone9,1', 'd10ap', 0x8010, 0x08],
        ['iPhone9,3', 'd101ap', 0x8010, 0x0A],
        ['iPhone9,2', 'd11ap', 0x8010, 0x0C],
        ['iPhone9,4', 'd111ap', 0x8010, 0x0E],

        // iPhone 8
        ['iPhone10,1', 'd20ap', 0x8015, 0x08],
        ['iPhone10,4', 'd201ap', 0x8015, 0x0A],
        ['iPhone10,2', 'd21ap', 0x8015, 0x0C],
        ['iPhone10,5', 'd211ap', 0x8015, 0x0E],

        // iPhone X
        ['iPhone10,3', 'd22ap', 0x8015, 0x10],
        ['iPhone10,6', 'd221ap', 0x8015, 0x12],

        // iPhone XR
        ['iPhone11,8', 'n841ap', 0x8020, 0x0C],

        // iPhone XS
        ['iPhone11,2', 'd321ap', 0x8020, 0x0E],
        ['iPhone11,6', 'd331ap', 0x8020, 0x0A], // Max
        
        // iPhone 11
        ['iPhone12,1', 'n104ap', 0x8030, 0x04], // 11
        ['iPhone12,3', 'd421ap', 0x8030, 0x06], // 11 Pro
        ['iPhone12,5', 'd431ap', 0x8030, 0x02], // 11 Pro Max
        ['iPhone12,8', 'd79ap', 0x8030, 0x08], // SE 2
    ];

    public static function getDeviceByProductType($productType) {
        $found = [];
        foreach (self::$devices as $dev) {
            if (strcasecmp($dev[0], $productType) === 0) {
                $found[] = $dev;
            }
        }
        if (count($found) > 1) {
             return $found;
        }
        if (count($found) === 1) {
            return $found[0];
        }
        return null;
    }

    public static function getDeviceByBoardConfig($boardConfig) {
        foreach (self::$devices as $dev) {
            if (strcasecmp($dev[1], $boardConfig) === 0) {
                return $dev;
            }
        }
        return null;
    }

    public static function getCPID($productType, $boardConfig = null) {
        if ($boardConfig) {
            $dev = self::getDeviceByBoardConfig($boardConfig);
            if ($dev) return $dev[2];
        }
        if ($productType) {
            $devs = self::getDeviceByProductType($productType);
            if (!$devs) return null;
            if (isset($devs[0]) && is_array($devs[0])) {
                return null;
            }
            return $devs[2];
        }
        return null;
    }
}
