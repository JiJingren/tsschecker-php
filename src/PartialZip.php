<?php

class PartialZip {
    private $url;
    private $size;
    private $cdEntries = [];
    
    public function __construct($url) {
        $this->url = $url;
    }

    private function request($range = null) {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "TSSChecker/1.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($range) {
            curl_setopt($ch, CURLOPT_RANGE, $range);
        } else {
            curl_setopt($ch, CURLOPT_NOBODY, true); // 发送 HEAD 请求获取大小
        }

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new Exception("Curl error: $err");
        
        if ($range) {
            return $data;
        } else {
            // 处理重定向后的 Content-Length
            return $info['download_content_length'] > 0 ? $info['download_content_length'] : $info['size_download'];
        }
    }

    public function init() {
        $this->size = $this->request();
        if ($this->size <= 0) {
            throw new Exception("Could not determine file size.");
        }
        $this->parseCentralDirectory();
    }

    private function parseCentralDirectory() {
        // 读取最后 64KB 查找 EOCD
        $readSize = min(65536, $this->size);
        $start = $this->size - $readSize;
        $end = $this->size - 1;
        $data = $this->request("$start-$end");

        // 查找 EOCD 签名: 0x06054b50
        $pos = strrpos($data, "\x50\x4b\x05\x06");
        if ($pos === false) {
            throw new Exception("EOCD not found.");
        }

        // 偏移 16: 中央目录相对于起始磁盘号的偏移量
        // 偏移 12: 中央目录的大小
        // 偏移 10: 该磁盘上中央目录的条目总数
        
        $eocd = substr($data, $pos);
        if (strlen($eocd) < 22) throw new Exception("Invalid EOCD size.");
        
        $cdSize = unpack("V", substr($eocd, 12, 4))[1];
        $cdOffset = unpack("V", substr($eocd, 16, 4))[1];
        $cdCount = unpack("v", substr($eocd, 10, 2))[1];

        // 获取中央目录
        $cdData = $this->request("$cdOffset-" . ($cdOffset + $cdSize - 1));
        
        // 解析中央目录条目
        $offset = 0;
        for ($i = 0; $i < $cdCount; $i++) {
            if ($offset + 46 > strlen($cdData)) break;
            
            $sig = unpack("V", substr($cdData, $offset, 4))[1];
            if ($sig !== 0x02014b50) break; // 无效签名

            $method = unpack("v", substr($cdData, $offset + 10, 2))[1];
            $compSize = unpack("V", substr($cdData, $offset + 20, 4))[1];
            $uncompSize = unpack("V", substr($cdData, $offset + 24, 4))[1];
            $nameLen = unpack("v", substr($cdData, $offset + 28, 2))[1];
            $extraLen = unpack("v", substr($cdData, $offset + 30, 2))[1];
            $commentLen = unpack("v", substr($cdData, $offset + 32, 2))[1];
            $localHeaderOffset = unpack("V", substr($cdData, $offset + 42, 4))[1];

            $name = substr($cdData, $offset + 46, $nameLen);
            
            $this->cdEntries[$name] = [
                'method' => $method,
                'compSize' => $compSize,
                'uncompSize' => $uncompSize,
                'offset' => $localHeaderOffset
            ];

            $offset += 46 + $nameLen + $extraLen + $commentLen;
        }
    }

    public function getFile($filename) {
        if (!isset($this->cdEntries[$filename])) {
            return null;
        }

        $entry = $this->cdEntries[$filename];
        
        // 读取本地文件头以查找数据偏移量
        $lhHeader = $this->request($entry['offset'] . "-" . ($entry['offset'] + 29));
        $sig = unpack("V", substr($lhHeader, 0, 4))[1];
        if ($sig !== 0x04034b50) throw new Exception("Invalid Local Header signature.");

        $nameLen = unpack("v", substr($lhHeader, 26, 2))[1];
        $extraLen = unpack("v", substr($lhHeader, 28, 2))[1];

        $dataStart = $entry['offset'] + 30 + $nameLen + $extraLen;
        $dataEnd = $dataStart + $entry['compSize'] - 1;

        if ($entry['compSize'] == 0) return "";

        $compressedData = $this->request("$dataStart-$dataEnd");

        if ($entry['method'] === 0) {
            // 仅存储
            return $compressedData;
        } elseif ($entry['method'] === 8) {
            // Deflated 压缩
            return gzinflate($compressedData);
        } else {
            throw new Exception("Unsupported compression method: " . $entry['method']);
        }
    }
    
    public function listFiles() {
        return array_keys($this->cdEntries);
    }
}
