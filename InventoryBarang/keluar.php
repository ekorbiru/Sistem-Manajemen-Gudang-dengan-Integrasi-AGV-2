<?php
require 'function.php';
require 'cek.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AWG Inventory - Barang Keluar</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body class="sb-nav-fixed">
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="index.php">AWG Inventory</a>
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
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Barang Keluar</h1>

                <!-- Form Pencarian -->
                <form method="GET" id="filterForm" class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" name="nama" placeholder="Cari Nama Barang" class="form-control" value="<?= isset($_GET['nama']) ? $_GET['nama'] : '' ?>" oninput="submitForm()">
                        </div>
                        <div class="col-md-4">
                            <input type="date" name="tanggal" class="form-control" value="<?= isset($_GET['tanggal']) ? $_GET['tanggal'] : '' ?>" oninput="submitForm()">
                        </div>
                    </form>

                <!-- Tabel Barang Keluar -->
                <div class="card mb-4">
                    <div class="card-header">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#keluarModal">- Keluarkan Barang</button>
                    </div>
                    <div class="card-body">
                        <table id="datatablesSimple">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
                                    <th>Tanggal Keluar</th>
                                    <th>Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                                    $nama = isset($_GET['nama']) ? $_GET['nama'] : '';
                                    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
                                    $lokasi = isset($_GET['lokasi']) ? $_GET['lokasi'] : '';

                                    $query = "SELECT barangkeluar.*, masukbarang.namabarang 
                                              FROM barangkeluar 
                                              JOIN masukbarang ON barangkeluar.idbarang = masukbarang.idbarang 
                                              WHERE 1=1";

                                    if ($nama != '') {
                                        $query .= " AND masukbarang.namabarang LIKE '%$nama%'";
                                    }
                                    if ($tanggal != '') {
                                        $query .= " AND DATE(tanggalkeluar) = '$tanggal'";
                                    }
                                    

                                    $ambilsemua = mysqli_query($conn, $query);
                                    $no = 1;
                                    while ($data = mysqli_fetch_array($ambilsemua)) {
                                        $namabarang = $data['namabarang'];
                                        $tanggal = $data['tanggalkeluar'];
                                        $jumlah = $data['jumlahkeluar'];
                                        
                                        echo "
                                            <tr>
                                                <td>$no</td>
                                                <td>$namabarang</td>
                                                <td>$tanggal</td>
                                                <td>$jumlah</td>
                                                
                                            </tr>
                                        ";
                                        $no++;
                                    }
                                    ?>
                                
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-4 bg-light mt-auto">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">AWG Inventory 2025</div>
                </div>
            </div>
        </footer>
    </div>
</div>



<!-- Modal Barang Keluar -->
<div class="modal fade" id="keluarModal" tabindex="-1" aria-labelledby="keluarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="keluarModalLabel">Keluarkan Barang</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <select name="idbarang" class="form-control" required>
                        <option value="">-- Pilih Barang --</option>
                        <?php
                        $stok = mysqli_query($conn, "SELECT * FROM masukbarang");
                        while ($row = mysqli_fetch_array($stok)) {
                            echo "<option value='{$row['idbarang']}'>{$row['namabarang']} (Stok: {$row['jumlahbarang']})</option>";
                        }
                        ?>
                    </select>
                    <br>
                    <input type="number" name="jumlahkeluar" placeholder="Jumlah Keluar" class="form-control" required>
                    <br>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="barangkeluar" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    function submitForm() {
        document.getElementById('filterForm').submit();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
<script src="js/datatables-simple-demo.js"></script>
<script>
    const datatablesSimple = document.getElementById('datatablesSimple');
    if (datatablesSimple) {
        new simpleDatatables.DataTable(datatablesSimple, {
            searchable: false
        });
    }
    </script>
</body>
</html>
