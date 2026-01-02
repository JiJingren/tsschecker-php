<?php
require_once __DIR__ . '/DeviceDB.php';
require_once __DIR__ . '/TssRequest.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/FirmwareAPI.php';
require_once __DIR__ . '/PartialZip.php';

class TssChecker {
    // 选项
    public $ecid = 0;
    public $device = null;
    public $boardconfig = null;
    public $manifestPath = null;
    public $savePath = null;
    public $nonce = null; // 16进制字符串
    public $generator = null; // 64位无符号整数
    public $noBaseband = false;
    public $quiet = false; // 添加静默模式属性
    public $iosVersion = null;
    public $buildVersion = null;
    
    public function __construct() {}

    private function log($msg) {
        if (!$this->quiet) {
            echo $msg;
        }
    }

    public function run() {
        // 验证输入
        $isTempManifest = false;
        if (!$this->manifestPath) {
            // 检查在线模式
            if ($this->device && ($this->iosVersion || $this->buildVersion)) {
                $this->log("Requesting firmware URL for {$this->device}...\n");
                $url = FirmwareAPI::getFirmwareUrl($this->device, $this->iosVersion, $this->buildVersion);
                if (!$url) {
                    $this->log("Error: Firmware not found for device {$this->device}.\n");
                    return false;
                }
                $this->log("Firmware URL: $url\n");
                
                $this->log("Downloading BuildManifest.plist...\n");
                try {
                    $zip = new PartialZip($url);
                    $zip->init();
                    $manifestData = $zip->getFile("BuildManifest.plist");
                    if (!$manifestData) {
                         $this->log("Error: BuildManifest.plist not found in IPSW.\n");
                         return false;
                    }
                    
                    // 保存到临时文件
                    $tempFile = tempnam(sys_get_temp_dir(), "BuildManifest");
                    file_put_contents($tempFile, $manifestData);
                    $this->manifestPath = $tempFile;
                    $isTempManifest = true;
                    $this->log("BuildManifest downloaded.\n");
                } catch (Exception $e) {
                    $this->log("Error downloading BuildManifest: " . $e->getMessage() . "\n");
                    return false;
                }
            } else {
                $this->log("Error: BuildManifest is required (or specify -d and -i for online mode).\n");
                return false;
            }
        }

        if (!file_exists($this->manifestPath)) {
            $this->log("Error: BuildManifest file not found: {$this->manifestPath}\n");
            return false;
        }

        // 解析 ECID
        if (is_string($this->ecid)) {
            // 检查是否带有前缀的16进制
            if (stripos($this->ecid, '0x') === 0) {
                $this->ecid = hexdec($this->ecid);
            } 
            // 检查是否不带前缀的16进制
            elseif (preg_match('/^[0-9a-fA-F]+$/', $this->ecid)) {
                $this->ecid = hexdec($this->ecid);
            }
        }

        // 获取设备信息
        $devInfo = null;
        if ($this->boardconfig) {
            $devInfo = DeviceDB::getDeviceByBoardConfig($this->boardconfig);
        } elseif ($this->device) {
            $devInfo = DeviceDB::getDeviceByProductType($this->device);
            if (is_array($devInfo) && isset($devInfo[0]) && is_array($devInfo[0])) {
                $this->log("Error: Ambiguous product type '{$this->device}'. Please specify board config (-B).\n");
                return false;
            }
        }
        
        if (!$devInfo) {
            $this->log("Error: Could not identify device. Please check -d or -B arguments.\n");
            return false;
        }

        list($prod, $board, $cpid, $bdid) = $devInfo;
        $this->log("Device: $prod, Board: $board, CPID: 0x" . dechex($cpid) . ", BDID: 0x" . dechex($bdid) . "\n");

        // 读取固件清单
        $manifestXml = file_get_contents($this->manifestPath);
        $manifestPlist = Plist::from_xml($manifestXml);
        if (!$manifestPlist) {
            $this->log("Error: Failed to parse BuildManifest.\n");
            return false;
        }

        // 选择构建标识
        $identities = Plist::dict_get_item($manifestPlist, "BuildIdentities");
        $selectedIdentity = null;
        
        if ($identities && $identities->type === Plist::PLIST_ARRAY) {
            foreach ($identities->children as $identity) {
                $iBoardId = Plist::dict_get_item($identity, "ApBoardID");
                $iChipId = Plist::dict_get_item($identity, "ApChipID");
                 
                $iBoardIdVal = $iBoardId ? $iBoardId->value : null;
                $iChipIdVal = $iChipId ? $iChipId->value : null;
                
                if (is_string($iBoardIdVal) && stripos($iBoardIdVal, '0x') === 0) $iBoardIdVal = hexdec($iBoardIdVal);
                if (is_string($iChipIdVal) && stripos($iChipIdVal, '0x') === 0) $iChipIdVal = hexdec($iChipIdVal);
                
                if ($iBoardIdVal == $bdid && $iChipIdVal == $cpid) {
                    $selectedIdentity = $identity;
                    break;
                }
            }
        }

        if (!$selectedIdentity) {
            $this->log("Error: Could not find BuildIdentity for this device in manifest.\n");
            return false;
        }
        
        $this->log("Found BuildIdentity.\n");

        // 创建请求
        $tssReq = new TssRequest($manifestPlist, "", true);
        $tssReq->setBuildIdentity($selectedIdentity);
        $tssReq->setBasebandEnabled(!$this->noBaseband);
        $tssReq->setDeviceVals($cpid, $bdid);
        if ($this->ecid) $tssReq->setEcid($this->ecid);
        
        $tssReq->addDefaultAPTagsToRequest();
        
        if ($this->nonce) {
             $tssReq->setApNonce(hex2bin($this->nonce));
        } elseif ($this->generator !== null) {
             $tssReq->setNonceGenerator($this->generator);
        } else {
             $this->log("Using random nonce generator.\n");
             $this->generator = mt_rand();
             $tssReq->setNonceGenerator($this->generator);
        }
        
        $tssReq->addAllAPComponentsToRequest();
        
        // 发送
        $this->log("Sending TSS request...\n");
        $response = $tssReq->getTSSResponse();
        
        if ($response) {
            $status = Plist::dict_get_item($response, "STATUS");
            $statusVal = $status ? $status->value : 0; // 如果缺失默认为 0
            
            if ($statusVal === 0) {
                $this->log("Signing successful!\n");
                
                // 将 Generator 添加到响应中
                if ($this->generator !== null) {
                    $genStr = "0x" . dechex($this->generator);
                    Plist::dict_set_item($response, "generator", Plist::new_string($genStr));
                }

                $xml = Plist::to_xml($response);
                
                if ($this->savePath) {
                    file_put_contents($this->savePath, $xml);
                    $this->log("Saved ticket to {$this->savePath}\n");
                }
                
                if ($isTempManifest) unlink($this->manifestPath);
                return $xml; // 成功时返回 XML 内容
            } else {
                $this->log("Signing failed (Status: $statusVal).\n");
                $message = Plist::dict_get_item($response, "MESSAGE");
                if ($message) $this->log("Message: " . $message->value . "\n");
                if ($isTempManifest) unlink($this->manifestPath);
                return false;
            }
        } else {
            $this->log("Error: Request failed.\n");
            if ($isTempManifest) unlink($this->manifestPath);
            return false;
        }

        if ($isTempManifest) unlink($this->manifestPath);
        return false;
    }
}
