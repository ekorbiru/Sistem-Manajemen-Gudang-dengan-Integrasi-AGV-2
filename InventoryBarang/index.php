<?php
require 'function.php';
require 'cek.php';
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

</head>
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
            <main>
                
            
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Stok Barang</h1>
                
                    <!-- Form Pencarian -->
                    <form method="GET" id="filterForm" class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" name="nama" placeholder="Cari Nama Barang" class="form-control" value="<?= isset($_GET['nama']) ? $_GET['nama'] : '' ?>" oninput="submitForm()">
                        </div>
                        <div class="col-md-4">
                            <input type="date" name="tanggal" class="form-control" value="<?= isset($_GET['tanggal']) ? $_GET['tanggal'] : '' ?>" oninput="submitForm()">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="lokasi" placeholder="Cari Lokasi" class="form-control" value="<?= isset($_GET['lokasi']) ? $_GET['lokasi'] : '' ?>" oninput="submitForm()">
                        </div>
                    </form>

                    <!-- Button Tambah Barang -->
                    <div class="card mb-4">
                        
                        <div class="card-body">
                            <table id="datatablesSimple">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Barang</th>
                                        <th>Tanggal Masuk</th>
                                        <th>Jumlah</th>
                                        <th>Lokasi</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php
                                    $nama = isset($_GET['nama']) ? $_GET['nama'] : '';
                                    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
                                    $lokasi = isset($_GET['lokasi']) ? $_GET['lokasi'] : '';

                                    $query = "SELECT * FROM masukbarang WHERE 1=1";
                                    if ($nama != '') {
                                        $query .= " AND namabarang LIKE '%$nama%'";
                                    }
                                    if ($tanggal != '') {
                                        $query .= " AND tanggalmasuk = '$tanggal'";
                                    }
                                    if ($lokasi != '') {
                                        $query .= " AND lokasi LIKE '%$lokasi%'";
                                    }

                                    $ambilsemuadatanya = mysqli_query($conn, $query);
                                    if (!$ambilsemuadatanya) {
                                        echo "<tr><td colspan='5'>Query error: " . mysqli_error($conn) . "</td></tr>";
                                    }

                                    while ($data = mysqli_fetch_array($ambilsemuadatanya)) {
                                        $idbarang = $data['idbarang'];
                                        $namabarang = $data['namabarang'];
                                        $tanggalmasuk = $data['tanggalmasuk'];
                                        $jumlahbarang = $data['jumlahbarang'];
                                        $lokasi = $data['lokasi'];
                                        echo "
                                            <tr>
                                                <td>$idbarang</td>
                                                <td>$namabarang</td>
                                                <td>$tanggalmasuk</td>
                                                <td>$jumlahbarang</td>
                                                <td>$lokasi</td>
                                            </tr>
                                        ";
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

    <!-- Modal Tambah Barang -->
    <div class="modal fade" id="myModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header">
                    <h4 class="modal-title">Tambahkan Barang</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form method="post">
                    <div class="modal-body">
                        <input type="text" name="namabarang" placeholder="Nama Barang" class="form-control" required>
                        <br>
                        <input type="number" name="jumlahbarang" placeholder="Jumlah Barang" class="form-control" required>
                        <br>
                        <select name="lokasi" class="form-control" required>
                            <option value="">-- Pilih Lokasi Lemari --</option>
                            <option value="Lemari A">Lemari A</option>
                            <option value="Lemari B">Lemari B</option>
                            <option value="Lemari C">Lemari C</option>
                            <option value="Lemari D">Lemari D</option>
                        </select>
                        <br>
                        <button type="submit" class="btn btn-primary" name="addnew">Submit</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Script -->
    <script>
        function submitForm() {
            document.getElementById('filterForm').submit();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
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
