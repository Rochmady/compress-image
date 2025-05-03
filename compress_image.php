<?php
session_start();

// Fungsi untuk menampilkan waktu mundur sesi
function format_time_left($expiryTime) {
    $timeLeft = $expiryTime - time();
    $minutes = floor($timeLeft / 60);
    $seconds = $timeLeft % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

// Hapus gambar yang kedaluwarsa
$currentTime = time();
if (isset($_SESSION['compressed_images'])) {
    $_SESSION['compressed_images'] = array_filter($_SESSION['compressed_images'], function($image) use ($currentTime) {
        return $image['expiry'] > $currentTime;
    });
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['image'])) {
        $file = $_FILES['image'];

        // Validasi ukuran file
        if ($file['size'] > 5 * 1024 * 1024) {
            $error = 'File size must be less than 5MB.';
        }

        // Validasi tipe file berdasarkan ekstensi
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = 'Only JPG and PNG files are allowed.';
        }

        // Validasi tipe MIME
        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        $fileMimeType = mime_content_type($file['tmp_name']);
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            $error = 'Only JPEG and PNG files are allowed.';
        }

        // Validasi dimensi gambar
        list($originalWidth, $originalHeight) = getimagesize($file['tmp_name']);
        $minWidth = 100;
        $minHeight = 100;
        $maxWidth = 5000;
        $maxHeight = 5000;
        if ($originalWidth < $minWidth || $originalHeight < $minHeight || $originalWidth > $maxWidth || $originalHeight > $maxHeight) {
            $error = 'Image dimensions must be between 100x100 and 5000x5000 pixels.';
        }

        if (empty($error)) {
            $originalData = file_get_contents($file['tmp_name']);
            $originalSize = strlen($originalData);

            // Dapatkan dimensi baru dari input pengguna
            $dimension = $_POST['dimension'] ?? '150';
            if (!empty($_POST['dimension_custom'])) {
                $dimension = $_POST['dimension_custom'];
            }

            $newWidth = (int)$dimension;
            $newHeight = (int)($dimension * $originalHeight / $originalWidth);
            if ($originalHeight > $originalWidth) {
                $newHeight = (int)$dimension;
                $newWidth = (int)($dimension * $originalWidth / $originalHeight);
            }

            // Gunakan ImageMagick convert untuk mengompresi gambar dengan kualitas tinggi
            $tmpOriginalPath = tempnam(sys_get_temp_dir(), 'orig');
            $tmpCompressedPath = tempnam(sys_get_temp_dir(), 'comp');

            move_uploaded_file($file['tmp_name'], $tmpOriginalPath);

            $cmd = "convert $tmpOriginalPath -resize {$newWidth}x{$newHeight} -quality 95 $tmpCompressedPath";
            exec($cmd);

            $compressedData = file_get_contents($tmpCompressedPath);
            unlink($tmpOriginalPath);
            unlink($tmpCompressedPath);

            $compressedSize = strlen($compressedData);

            // Tambahkan gambar terkompresi ke session
            $expiryTime = time() + 300; // 5 menit dari sekarang
            if (!isset($_SESSION['compressed_images'])) {
                $_SESSION['compressed_images'] = [];
            }
            array_unshift($_SESSION['compressed_images'], [
                'name' => $file['name'],
                'type' => $fileMimeType === 'image/jpeg' ? 'JPEG' : 'PNG',
                'original_data' => base64_encode($originalData),
                'original_size' => $originalSize,
                'data' => base64_encode($compressedData),
                'compressed_size' => $compressedSize,
                'expiry' => $expiryTime
            ]);

            // Redirect untuk menghindari resubmission
            header('Location: compress_image.php');
            exit();
        }
    }
}

// Tampilkan form dan gambar terkompresi
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
	<title>Resize Your Image | Sangia Publishing</title>
    <link rel="apple-touch-icon" sizes="57x57" href="//assets.sangia.org/static/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="//assets.sangia.org/static/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="//assets.sangia.org/static/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="//assets.sangia.org/static/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="//assets.sangia.org/static/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="//assets.sangia.org/static/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="//assets.sangia.org/static/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="//assets.sangia.org/static/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="//assets.sangia.org/static/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="//assets.sangia.org/static/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="//assets.sangia.org/static/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="//assets.sangia.org/static/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="//assets.sangia.org/static/favicon/favicon-16x16.png">
    <link rel="manifest" href="//assets.sangia.org/static/favicon/manifest.json">	
    <style type="text/css">
        *,
        *:before,
        *:after {
            box-sizing: border-box;
        }

        html {
            font-size: 62.5%;
        }

        html,
        body {
            height: 100%;
        }

        body {
            display: table;
            font-size: 1.6rem;
            width: 100%;
            margin: 0;
            background-color: white;
            color: #333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-weight: 400;
            line-height: 1.57895;
            position: relative;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1 {
            line-height: 1.57895;
            color: rgb(142, 142, 142);
            margin: 0 0 1.27rem;
            font-size: 2.7rem;
            padding: 0;
            font-weight: 700;
        }

        a {
            color: #0067c5;
            text-decoration: none;
        }

        a:focus, a:active, a:hover {
            color: #346393;
            text-decoration: underline;
        }

        .p-container {
            margin: auto;
            padding: 0px 16px;
            max-width: 1140px;
        }

        @media (min-width: 43.76em) {
            .p-container {
                padding: 0px 24px;
            }
        }

        .p-main {
            margin-bottom: 72px;
        }

        .p-table-row {
            display: table-row;
            height: 1px;
        }

        .p-table-row--expanded {
            height: 100%;
        }

        .c-footer-corporate {
            background-color: #f2f2f2;
            border-top: 1px solid #e4e4e4;
            border-bottom: 7px solid #f06;
            padding:16px 0 24px;
        }
        .p-table-row--expanded:before {
            background: url(//sangia.org/assets/img/sangia-mono-branded-v1.png) no-repeat;
            content: "";
            display: block;
            height: 89px;
            width: 7.43455%;
            position: absolute;
            right: 183px;
            top: 12px;         
        }
        
        .results {
            
        }
        .input_role { margin-bottom: 7px; }
        .results thead {
            border-bottom: 1px solid rgb(142, 142, 142);
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 7px; text-align: center; }
        .results tr:not(:last-child) {border-bottom: 1px solid #bcbcbc;}
        .fade-out { opacity: 1; transition: opacity 2s ease-out; }
        .message { margin-top: 20px; color: green; }
        .error { color: red; }
        input, select {
            background: #fff;
            border-radius: 3px;
            font-weight: 400;
            margin-bottom: 0;
            padding: 5px;
            white-space: normal;
            border: 1px solid #bcbcbc;
            font-size: 1.6rem;
            font-family: inherit;
        }
        select, option {
            min-width: 70px; 
            text-align: end;
            font-size: 1.6rem;
            font-family: inherit;
        }
        button {
            background-color: #0176c3;
            border-radius: 5px;
            border: 1px solid #0176c3;
            cursor: pointer;
            padding: 9px 12px;
            outline: 0;
            color: #fff;
            font-size: 1.4rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.25);
        }

        .c-footer-corporate__legal {
            font-size: 1.4rem;
            margin: 0;
            line-height: 1.4;
        }

        .c-footer-corporate__logo {
            display: block;
        }

        .c-footer-corporate__link {
            text-decoration: underline;
            color: #000;
        }

        .c-footer-corporate__link:visited {
            color: #000;
        }

        .c-footer-corporate__link:hover {
            color: #000;
            text-decoration: none;
        }

        .c-footer-corporate__link:active {
            color: #eb2a60;
            outline: 1px dotted #eb2a60;
        }

        .c-header {
            margin-bottom: 24px;
            background-color: #f5f5f5;
            padding: 24px 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .c-header-logo {
            display: inline-block;
            vertical-align: middle;
            padding-right: 20px;
            max-width: 27rem;
        }

        .c-header-logo + .c-header-logo {
            padding-left: 20px;
            border-left: 1px solid #b0a8a3;
        }
    </style>
</head>
<body data-origin="app">
<div class="p-table-row">
    <header class="c-header">
        <div class="p-container">
            <a href="//www.journals.sangia.org/ISLE"><img class="c-header-logo" src="https://assets.sangia.org/img/index.png" alt="Sangia Publishing"></a>
        </div>
    </header>
</div>
<div class="p-table-row--expanded">
	<section class="p-main p-container content">
        <h1>Resize Your Image</h1>
        <form method="post" enctype="multipart/form-data">
            <section class="input_role">
                <label for="image">Choose an image to resize (JPG or PNG, max 5MB):</label>
                <input type="file" id="image" name="image" required>
                <button type="submit">Go Resize</button>
            </section>
            <section class="dimension">
                <label for="dimension">Choose a dimension:</label>
                <select id="dimension" name="dimension">
                    <option value="150">150</option>
                    <option value="300">300</option>
                    <option value="450">450</option>
                    <option value="600">600</option>
                </select>
                <label for="dimension_custom">Or specify a custom dimension (e.g., 200):</label>
                <input type="text" id="dimension_custom" name="dimension_custom" placeholder="Width or Height">
            </section>
        </form>
        
    	<section class="results">
            <?php if (!empty($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
        
            <?php if (!empty($_SESSION['compressed_images'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Original File</th>
                            <th>Resize Result</th>
                            <th>View Result Resize</th>
                            <th>Session Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($_SESSION['compressed_images'] as $index => $image): ?>
                        <?php
                        $originalImageSrc = 'data:image/' . ($image['type'] === 'JPEG' ? 'jpeg' : 'png') . ';base64,' . $image['original_data'];
                        $compressedImageSrc = 'data:image/' . ($image['type'] === 'JPEG' ? 'jpeg' : 'png') . ';base64,' . $image['data'];
                        $fileExtension = $image['type'] === 'JPEG' ? 'jpg' : 'png';
                        $expiryTime = $image['expiry'];
                        $originalSizeInKB = round($image['original_size'] / 1024, 2) . ' KB';
                        $compressedSizeInKB = round($image['compressed_size'] / 1024, 2) . ' KB';
                        ?>
                        <tr id="row-<?php echo $index; ?>">
                            <td>
                                <img src="<?php echo $originalImageSrc; ?>" alt="Original Image" style="max-width:150px; max-height:150px;">
                                <br><?php echo $image['name']; ?><br><?php echo $originalSizeInKB; ?>
                            </td>
                            <td>
                                <img src="<?php echo $compressedImageSrc; ?>" alt="Compressed Image" style="max-width:150px; max-height:150px;">
                                <br>sangia_compressed_image.<?php echo $fileExtension; ?><br><?php echo $compressedSizeInKB; ?>
                            </td>
                            <td><a href="<?php echo $compressedImageSrc; ?>" target="_blank">View</a></td>
                            <td><span id="time-<?php echo $index; ?>"><?php echo format_time_left($expiryTime); ?></span></td>
                            <td>
                                <button type="button" onclick="downloadImage('<?php echo $compressedImageSrc; ?>', 'sangia_compressed_image.<?php echo $fileExtension; ?>')">Download</button>
                                <button type="button" onclick="deleteImage(<?php echo $index; ?>)">Delete</button>
                            </td>
                        </tr>
                        <script>
                            (function countdown(index, expiryTime) {
                                var timeLeft = expiryTime - Math.floor(Date.now() / 1000);
                                if (timeLeft > 0) {
                                    var minutes = Math.floor(timeLeft / 60);
                                    var seconds = timeLeft % 60;
                                    document.getElementById('time-' + index).textContent = minutes + ':' + ('0' + seconds).slice(-2);
                                    setTimeout(function () {
                                        countdown(index, expiryTime);
                                    }, 1000);
                                } else {
                                    document.getElementById('row-' + index).style.display = 'none';
                                }
                            })(<?php echo $index; ?>, <?php echo $expiryTime; ?>);
                        </script>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
        </section>
        
	</section>
</div>	
<div class="p-table-row">
    <footer>
        <div class="c-footer-corporate">
            <div class="p-container">
                <img class="c-footer-corporate-logo" src="https://www.journals.sangia.org/public/site/images/cosire-foother.svg" alt="Sangia Publishing">
                <p class="c-footer-corporate__legal">© 2017 Sangia Reserach Media & Publishing unless otherwise stated. Part of 
                    <a class="c-footer-corporate__link" href="//www.publishing.sangia.org">Sangia Research Media & Publishing</a>.</p>
            </div>
        </div>
    </footer>
</div>

    <script>
        function downloadImage(uri, filename) {
            var link = document.createElement('a');
            link.href = uri;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function deleteImage(index) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'delete_image.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('row-' + index).style.display = 'none';
                }
            };
            xhr.send('index=' + index);
        }
    </script>
</body>
</html>
