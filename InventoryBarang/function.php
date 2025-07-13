<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "InventoryBarang");


//tambah barang

//keluar
if (isset($_POST['barangkeluar'])) {
    $idbarang = $_POST['idbarang'];
    $jumlahkeluar = $_POST['jumlahkeluar'];
    $tanggal = date('Y-m-d');

    // cek stok dari masukbarang
    $cek = mysqli_query($conn, "SELECT * FROM masukbarang WHERE idbarang='$idbarang'");
    $data = mysqli_fetch_array($cek);
    $stok = $data['jumlahbarang'];

    if ($stok >= $jumlahkeluar) {
        // kurangi stok di masukbarang
        $kurangi = $stok - $jumlahkeluar;
        $update = mysqli_query($conn, "UPDATE masukbarang SET jumlahbarang='$kurangi' WHERE idbarang='$idbarang'");

        // simpan ke barangkeluar
        $insert = mysqli_query($conn, "INSERT INTO barangkeluar (idbarang, tanggalkeluar, jumlahkeluar) 
                                       VALUES ('$idbarang', '$tanggal', '$jumlahkeluar')");

        if ($update && $insert) {
            echo "<script>alert('Berhasil mengeluarkan barang'); window.location.href='keluar.php';</script>";
        } else {
            echo "<script>alert('Gagal menyimpan ke database');</script>";
        }
    } else {
        echo "<script>alert('Stok tidak cukup');</script>";
    }
}

if (isset($_POST['addnew'])) {
    $namabarang = $_POST['namabarang'];
    $jumlah = $_POST['jumlahbarang'];
    $lokasi = $_POST['lokasi'];

    // Cek apakah barang sudah ada di master
    $cek = mysqli_query($conn, "SELECT * FROM masukbarang WHERE namabarang='$namabarang'");
    
    if (mysqli_num_rows($cek) > 0) {
        // Barang sudah ada → update stok
        $data = mysqli_fetch_array($cek);
        $idbarang = $data['idbarang'];
        $stoklama = $data['jumlahbarang'];
        $stokbaru = $stoklama + $jumlah;

        $update = mysqli_query($conn, "UPDATE masukbarang SET jumlahbarang='$stokbaru' WHERE idbarang='$idbarang'");

        // Catat ke riwayat masuk
        $riwayat = mysqli_query($conn, "INSERT INTO riwayatmasuk (idbarang, jumlahmasuk, lokasi) VALUES ('$idbarang', '$jumlah', '$lokasi')");
    } else {
        // Barang belum ada → masukkan ke master dan riwayat
        $tambahbarang = mysqli_query($conn, "INSERT INTO masukbarang (namabarang, jumlahbarang, lokasi) VALUES ('$namabarang', '$jumlah', '$lokasi')");

        if ($tambahbarang) {
            $idbarang = mysqli_insert_id($conn);
            $riwayat = mysqli_query($conn, "INSERT INTO riwayatmasuk (idbarang, jumlahmasuk, lokasi) VALUES ('$idbarang', '$jumlah', '$lokasi')");
        }
    }

    if ($update || $tambahbarang) {
        echo '<script>window.location.href="masuk.php";</script>';
    }
}


?>


