<?php
// example.php
// Web 环境下的 TssChecker 测试脚本

// 引入核心类
require_once 'src/TssChecker.php';

// 获取 Manifest 目录下的所有 plist 文件
$manifestDir = __DIR__ . '/Manifest';
$manifestFiles = [];
if (is_dir($manifestDir)) {
    $files = scandir($manifestDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'plist') {
            $manifestFiles[] = $file;
        }
    }
}

// 处理表单提交
$message = '';
$logs = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device = trim($_POST['device'] ?? '');
    $mode = $_POST['mode'] ?? 'local';
    $manifest = $_POST['manifest'] ?? '';
    $ios_version = trim($_POST['ios_version'] ?? '');
    $build_version = trim($_POST['build_version'] ?? '');
    $ecid = trim($_POST['ecid'] ?? '');
    $generator = trim($_POST['generator'] ?? '');
    $boardconfig = trim($_POST['boardconfig'] ?? '');
    $apnonce = trim($_POST['apnonce'] ?? '');
    $no_baseband = isset($_POST['no_baseband']);
    $filename = trim($_POST['filename'] ?? '');
    $show_logs = isset($_POST['show_logs']);

    $checker = new TssChecker();
    $checker->device = $device;
    $checker->ecid = $ecid;
    if ($boardconfig !== '') $checker->boardconfig = $boardconfig;
    if ($apnonce !== '') $checker->nonce = $apnonce;
    if ($no_baseband) $checker->noBaseband = true;
    if ($generator !== '') {
        if (stripos($generator, '0x') === 0) $checker->generator = hexdec($generator);
        else $checker->generator = intval($generator);
    }

    if ($mode === 'online') {
        if ($ios_version === '' && $build_version === '') {
            $message = '<div class="alert alert-warning">请填写 iOS 版本或 Build 版本。</div>';
        } else {
            if ($ios_version !== '') $checker->iosVersion = $ios_version;
            if ($build_version !== '') $checker->buildVersion = $build_version;
        }
    } else {
        if ($manifest === '') {
            $message = '<div class="alert alert-warning">请选择本地 Manifest 文件。</div>';
        } else {
            $checker->manifestPath = $manifestDir . '/' . $manifest;
        }
    }

    if ($message === '') {
        if ($show_logs) {
            $checker->quiet = false;
            ob_start();
        } else {
            $checker->quiet = true;
        }

        $shshContent = $checker->run();

        if ($show_logs) {
            $logs = ob_get_clean();
        }

        if ($shshContent) {
            $downloadName = $filename !== '' ? $filename : ($ecid . '_' . $device . '.shsh');
            header('Content-Description: File Transfer');
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="'.basename($downloadName).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($shshContent));
            echo $shshContent;
            exit;
        } else {
            $message = '<div class="alert alert-danger">获取 SHSH 失败！请检查参数或日志。</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TSSChecker Web Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f5f5f7; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #1d1d1f; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #1d1d1f; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d2d2d7; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #0071e3; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #0077ed; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 8px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
        .note { font-size: 12px; color: #86868b; margin-top: 5px; }
        .logs { background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 8px; overflow: auto; max-height: 300px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>TSSChecker Web Interface</h1>
        <?php echo $message; ?>
        <?php if (!empty($logs)): ?>
            <div class="form-group">
                <label>日志输出</label>
                <pre class="logs"><?php echo htmlspecialchars($logs); ?></pre>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="device">设备型号 (Device Identifier)</label>
                <input type="text" id="device" name="device" placeholder="例如: iPhone4,1" value="iPhone4,1" required>
            </div>
            
            <div class="form-group">
                <label for="ecid">ECID (Hex/Dec)</label>
                <input type="text" id="ecid" name="ecid" placeholder="例如: 000000BD281EADC8" value="000000BD281EADC8" required>
            </div>

            <div class="form-group">
                <label>模式 (Mode)</label>
                <div style="margin-top: 5px;">
                    <label style="display:inline; margin-right:15px; font-weight:normal;">
                        <input type="radio" name="mode" value="local" checked onclick="toggleMode()" style="width:auto;"> 本地 Manifest
                    </label>
                    <label style="display:inline; font-weight:normal;">
                        <input type="radio" name="mode" value="online" onclick="toggleMode()" style="width:auto;"> 在线下载 (Online)
                    </label>
                </div>
            </div>

            <div class="form-group" id="local_group">
                <label for="manifest">固件清单 (BuildManifest)</label>
                <select id="manifest" name="manifest">
                    <?php foreach ($manifestFiles as $file): ?>
                        <option value="<?php echo htmlspecialchars($file); ?>" <?php echo ($file === 'iPhone4,1_8.4.1.plist') ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($file); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="online_group" style="display:none;">
                <label for="ios_version">iOS 版本 (iOS Version)</label>
                <input type="text" id="ios_version" name="ios_version" placeholder="例如: 9.3.6">
                <div class="note">也可填写 Build 版本号（如: 13G37）</div>
                <label for="build_version" style="margin-top:10px;">Build 版本 (Build Version)</label>
                <input type="text" id="build_version" name="build_version" placeholder="例如: 13G37">
            </div>

            <script>
                function toggleMode() {
                    var mode = document.querySelector('input[name="mode"]:checked').value;
                    document.getElementById('local_group').style.display = mode === 'local' ? 'block' : 'none';
                    document.getElementById('online_group').style.display = mode === 'online' ? 'block' : 'none';
                }
            </script>

            <div class="form-group">
                <label for="generator">Generator (可选)</label>
                <input type="text" id="generator" name="generator" placeholder="例如: 0x1111111111111111" value="0x1111111111111111">
                <div class="note">如果不填写，将使用随机生成的 Generator。</div>
            </div>

            <div class="form-group">
                <label for="boardconfig">板号 (BoardConfig，可选)</label>
                <input type="text" id="boardconfig" name="boardconfig" placeholder="如: n94ap">
            </div>

            <div class="form-group">
                <label for="apnonce">APNonce (可选)</label>
                <input type="text" id="apnonce" name="apnonce" placeholder="16进制字符串，如: 7d...">
            </div>

            <div class="form-group">
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" id="no_baseband" name="no_baseband" style="width:auto;"> 不检查基带 (No Baseband)
                </label>
            </div>

            <div class="form-group">
                <label for="filename">保存文件名 (可选)</label>
                <input type="text" id="filename" name="filename" placeholder="默认: ECID_设备.shsh">
            </div>

            <div class="form-group">
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" id="show_logs" name="show_logs" style="width:auto;"> 显示日志
                </label>
                <div class="note">若勾选，将在页面展示签署过程日志。</div>
            </div>

            <button type="submit">获取 SHSH 票据</button>
        </form>
    </div>
</body>
</html>
