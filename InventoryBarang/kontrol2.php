<?php
require 'function.php';
require 'cek.php';
?>

<?php
$rpm = null;
$duration = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rpm'])) {
    $target_rpm = (int) $_POST['rpm'];

    $url = "http://localhost:5000/run_motor";
    $data = json_encode(["rpm1" => $target_rpm, "rpm2" => $target_rpm]);


    $options = [
        "http" => [
            "header"  => "Content-type: application/json\r\n",
            "method"  => "POST",
            "content" => $data,
        ],
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        $error = "Gagal menghubungi motor API.";
    } else {
        $result = json_decode($response, true);
        if (isset($result['error'])) {
            $error = "Error dari motor: " . $result['error'];
        } else {
            if (isset($result['rpm1']) && isset($result['rpm2'])) {
                $rpm1 = (int)$result['rpm1'];
                $rpm2 = (int)$result['rpm2'];
    
                // Simpan ke tabel rpm_log
                $query_save = "INSERT INTO rpm_log (rpm1, rpm2, waktu) VALUES ($rpm1, $rpm2, NOW())";
                if (mysqli_query($conn, $query_save)) {
                    echo "Data berhasil disimpan ke database.";
                } else {
                    echo "Gagal menyimpan: " . mysqli_error($conn);
                }
            }
        }
    }    
}

if (isset($_POST['start_line'])) {
    $ch = curl_init('http://127.0.0.1:5000/start_linefollower'); // Pastikan sesuai dengan Flask
    curl_setopt($ch, CURLOPT_POST, true); // ini membuatnya POST
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // kosong karena tidak ada body dibutuhkan

    $response = curl_exec($ch);
    curl_close($ch);

    echo "<pre>▶️ Start Line Follower: $response</pre>";
}

if (isset($_POST['stop_line'])) {
    $ch = curl_init('http://127.0.0.1:5000/stop_linefollower');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');

    $response = curl_exec($ch);
    curl_close($ch);

    echo "<pre>🛑 Stop Response: $response</pre>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['arah'])) {
    $arah = $_POST['arah'];
    $rpm1 = 5;
    $rpm2 = 5;
    

    switch ($arah) {
        case 'maju':
            $rpm1 = 10;
            $rpm2 = 10;
            break;
        case 'extra_left':
            $rpm1 = 10;
            $rpm2 = 4;
            break;
        case 'exxtra_left':
            $rpm1 = -10;
            $rpm2 = -4;
            break;
        case 'extra_right':
            $rpm1 = 4;
            $rpm2 = 10;
            break;
        case 'exxtra_right':
            $rpm1 = -4;
            $rpm2 = -10;
            break;
        case 'mundur':
            $rpm1 = -10;
            $rpm2 = -10;
            break;
        case 'kiri':
            $rpm1 = 10; // motor kiri mundur
            $rpm2 = -10; // motor kanan mundur → robot putar kiri
            break;
        case 'kanan':
            $rpm1 = -10;  // motor kiri maju
            $rpm2 = 10;  // motor kanan maju → robot putar kanan
            break;
        case 'stop':
            $rpm1 = 0;
            $rpm2 = 0;
            break;
    }

    $data = json_encode([
        "rpm1" => $rpm1,
        "rpm2" => $rpm2,
        
    ]);

    $ch = curl_init('http://127.0.0.1:5000/run_motor');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    echo "<pre>RESPON MURNI API:\n$result</pre>";
    
    $response = json_decode($result, true);
    print_r($response);

    if (isset($response['rpm1']) && isset($response['rpm2'])) {
        $rpm1 = (int)$response['rpm1'];
        $rpm2 = (int)$response['rpm2'];
        
        $query_save = "INSERT INTO rpm_log (rpm1, rpm2, waktu) VALUES ($rpm1, $rpm2, NOW())";
        if (mysqli_query($conn, $query_save)) {
            echo "✔️ Data arah disimpan.";
        } else {
            echo "❌ Gagal simpan arah: " . mysqli_error($conn);
        }
    }    
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>AWG Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <!-- <meta http-equiv="refresh" content="5"> Refresh halaman tiap 5 detik -->
    <style>
        .controller {
            display: grid;
            grid-template-areas:
                "extra-left up extra-right"
                "left center right"
                "exxtra-left down exxtra-right";
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 50px;
        }
        button {
            width: 80px;
            height: 80px;
            font-size: 20px;
            font-weight: bold;
        }
        .up { grid-area: up; }
        .extra-left {grid-area: extra-left;}
        .exxtra-left {grid-area: exxtra-left;}
        .extra-right {grid-area: extra-right;}
        .exxtra-right {grid-area: exxtra-right;}
        .down { grid-area: down; }
        .left { grid-area: left; }
        .right { grid-area: right; }
        .center { grid-area: center; }

        
    </style>
</head>


<script>
function kirimArah(arah) {
    fetch('kontrol2.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'arah=' + encodeURIComponent(arah)
    })
    .then(response => response.text())
    .then(result => {
        console.log("Respons API:", result);
        // Kamu bisa juga tampilkan di halaman jika mau
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand ps-3" href="index.php">AWG Inventory</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <a class="nav-link" href="index.php"><div class="sb-nav-link-icon"><i class="fas fa-box"></i></div>Stok Barang</a>
                        <a class="nav-link" href="masuk.php"><div class="sb-nav-link-icon"><i class="fas fa-arrow-down"></i></div>Barang Masuk</a>
                        <a class="nav-link" href="keluar.php"><div class="sb-nav-link-icon"><i class="fas fa-arrow-up"></i></div>Barang Keluar</a>
                        <a class="nav-link" href="kontrol2.php"><div class="sb-nav-link-icon"><i class="fas fa-edit"></i></div>Kontrol Motor</a>
                        <a class="nav-link" href="logout.php">Logout</a>
                    </div>
                </div>
            </nav>
        </div>

        <div id="layoutSidenav_content">
            
            <h1>Kontrol Motor DDSM115</h1>

            

            <?php if ($error): ?>
                <p style="color:red;"><?= $error ?></p>
            <?php elseif ($rpm !== null && $duration !== null): ?>
                <h2>Hasil Motor</h2>
                <p>RPM: <?= $rpm ?></p>
                
            <?php endif; ?>
                <div class="container-fluid px-4">
                    <br>
                    </br>
                    <?php
                    // Ambil data RPM terbaru
                    $query_rpm = "SELECT rpm1, rpm2, waktu FROM rpm_log ORDER BY waktu DESC LIMIT 1";
                    $result_rpm = mysqli_query($conn, $query_rpm);
                    $rpm_data = $result_rpm ? mysqli_fetch_assoc($result_rpm) : null;
                    ?>

                    <div class="alert alert-info" role="alert">
                        <strong>RPM Kiri:</strong> <span id="rpm_kiri">-</span> |
                        <strong>RPM Kanan:</strong> <span id="rpm_kanan">-</span><br>
                        <small><em>Waktu: <span id="waktu_rpm">-</span></em></small>
                    </div>





                </div>

                <form class="controller">
                    <button type="button" onclick="kirimArah('maju')" class="up">↑</button>
                    <button type="button" onclick="kirimArah('extra_left')" class="extra-left">EL</button>
                    <button type="button" onclick="kirimArah('exxtra_left')" class="exxtra-left">ExL</button>
                    <button type="button" onclick="kirimArah('extra_right')" class="extra-right">ER</button>
                    <button type="button" onclick="kirimArah('exxtra_right')" class="exxtra-right">ExR</button>
                    <button type="button" onclick="kirimArah('mundur')" class="down">↓</button>
                    <button type="button" onclick="kirimArah('kiri')" class="left">←</button>
                    <button type="button" onclick="kirimArah('kanan')" class="right">→</button>
                    <button type="button" onclick="kirimArah('stop')" class="center" style="background-color:red; color:white;">■</button>
                    <!-- <button name="arah" value="extra_left" class="extra-left">EL</button>
                    <!-- <button type="button" onmousedown="kirimArah('maju')" onmouseup="kirimArah('stop')" class="up">↑</button>
                    <button type="button" onmousedown="kirimArah('extra_left')" onmouseup="kirimArah('stop')" class="extra-left">EL</button>
                    <button type="button" onmousedown="kirimArah('exxtra_left')" onmouseup="kirimArah('stop')" class="exxtra-left">ExL</button>
                    <button type="button" onmousedown="kirimArah('extra_right')" onmouseup="kirimArah('stop')" class="extra-right">ER</button>
                    <button type="button" onmousedown="kirimArah('exxtra_right')" onmouseup="kirimArah('stop')" class="exxtra-right">ExR</button>
                    <button type="button" onmousedown="kirimArah('mundur')" onmouseup="kirimArah('stop')" class="down">↓</button>
                    <button type="button" onmousedown="kirimArah('kiri')" onmouseup="kirimArah('stop')" class="left">←</button>
                    <button type="button" onmousedown="kirimArah('kanan')" onmouseup="kirimArah('stop')" class="right">→</button>
                    <button type="button" onclick="kirimArah('stop')" class="center" style="background-color:red; color:white;">■</button> -->
    
                </form>

                <form method="post">
                    <button type="submit" name="start_line" style="font-size: 12px;">Start Line Follower</button>
                    <button type="submit" name="stop_line" style="font-size: 12px;">Stop Line Follower</button>
                </form>
               

            

                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">AWG Inventory 2025</div>
                        </div>
                    </div>
                </footer>
        </div>
    </div>

    

    <script>
    function ambilRPM() {
        fetch('get_latest_rpm.php')
            .then(res => res.json())
            .then(data => {
                document.getElementById('rpm_kiri').textContent = data.rpm1;
                document.getElementById('rpm_kanan').textContent = data.rpm2;
                document.getElementById('waktu_rpm').textContent = data.waktu;
            });
    }

    setInterval(ambilRPM, 500);
    </script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script src="js/datatables-simple-demo.js"></script>


</body>
</html>
