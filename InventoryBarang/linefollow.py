import logging
import time
import struct
import queue
import threading
from collections import deque
import signal
import sys
import requests
import serial
import serial.rs485
import crcmod.predefined
from pymodbus.client import ModbusSerialClient

import signal
import sys

agv = None

def handle_sigterm(signum, frame):
    global agv
    print("🛑 Menerima SIGTERM. Menghentikan AGV dengan aman...")
    if agv:
        agv.stop()
    sys.exit(0)

signal.signal(signal.SIGTERM, handle_sigterm)


# ==============================================================================
# 1. KONFIGURASI UTAMA (Digabungkan dari file config.py)
# ==============================================================================

# Port Serial
WHEEL_PORT = "COM29"      # Ganti dengan port motor Anda
SENSOR_PORT = "COM28"     # Ganti dengan port sensor Anda

# Baudrate
WHEEL_BAUDRATE = 115200
SENSOR_BAUDRATE = 9600

# Parameter Motor
MOTOR_ID_LEFT = 2        # ID motor kiri
MOTOR_ID_RIGHT = 1       # ID motor kanan
MAX_RPM = 20             # Kecepatan maksimum AGV dalam RPM, sesuai permintaan Anda.
REVERSE_RIGHT = True     # atau False tergantung kebutuhan arah motor kanan


# Parameter Sensor
SENSOR_ADDRESS = 1

# Logika Line Following
IDEAL_CENTER_SENSOR = 8.5  # Titik tengah ideal antara sensor 8 dan 9

# Konstanta PID (Diperbarui sesuai permintaan Anda, Ki=0 berarti kontrol P-D)
PID_KP = 2.0   # Proportional - Seberapa kuat reaksi terhadap error saat ini.
PID_KI = 0.0   # Integral - (Non-aktif) Mengkoreksi error yang terakumulasi.
PID_KD = 0.5   # Derivative - Mencegah overshooting dengan melihat laju perubahan error.

# Pengaturan Waktu
LOOP_DELAY_S = 0.05 # Waktu tunda utama loop dalam detik (50ms -> 20 Hz)

# Setup Logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger("AGV_LineFollower")

# ==============================================================================
# 2. KELAS KONTROL MOTOR (Diadaptasi dari kode Anda)
# ==============================================================================

def kirim_rpm_ke_api(rpm1, rpm2):
    try:
        requests.post(
            "http://127.0.0.1:5000/log_rpm",
            json={
                "rpm1": rpm1,
                "rpm2": rpm2
            },
            timeout=0.5
        )
    except Exception as e:
        print(f"❌ Gagal kirim RPM: {e}")


class MotorControl:
    """Mengelola komunikasi serial tingkat rendah ke motor DDSM115."""
    def __init__(self, device, baudrate):
        self.device = device
        self.baudrate = baudrate
        self.ser = None
        self.crc8 = crcmod.predefined.mkPredefinedCrcFun('crc-8-maxim')
        self.str_9bytes = ">BBBBBBBBB"
        self.connect()

    def connect(self):
        """Membuka atau mencoba membuka kembali koneksi port serial."""
        if self.ser and self.ser.is_open:
            logger.info("Port serial sudah terbuka.")
            return True
        try:
            self.ser = serial.rs485.RS485(self.device, self.baudrate, timeout=0.05)
            self.ser.rs485_mode = serial.rs485.RS485Settings()
            logger.info(f"Port serial {self.device} berhasil dibuka.")
            return True
        except serial.SerialException as e:
            logger.error(f"Gagal membuka port serial {self.device}: {e}")
            self.ser = None
            return False

    def close(self):
        """Menutup koneksi port serial."""
        if self.ser and self.ser.is_open:
            self.ser.close()
            logger.info(f"Port serial {self.device} ditutup.")

    def _int_16_to_bytes(self, data: int):
        return [(data & 0xFF00) >> 8, data & 0x00FF]

    def _crc_attach(self, data_bytes: bytes):
        crc_int = self.crc8(data_bytes)
        return data_bytes + crc_int.to_bytes(1, 'big')

    def send_rpm(self, motor_id: int, rpm: int):
        """Mengirim perintah RPM ke motor tertentu."""
        if not self.ser or not self.ser.is_open:
            logger.warning(f"Port serial tidak terbuka. Mencoba menyambung kembali...")
            if not self.connect():
                return False
        
        rpm_clamped = int(max(-MAX_RPM, min(rpm, MAX_RPM)))
        rpm_bytes = self._int_16_to_bytes(rpm_clamped)
        
        cmd_bytes = struct.pack(self.str_9bytes, motor_id, 0x64, rpm_bytes[0], rpm_bytes[1], 0, 0, 0, 0, 0)
        cmd_bytes_with_crc = self._crc_attach(cmd_bytes)

        try:
            self.ser.write(cmd_bytes_with_crc)
            # logger.debug(f"Mengirim ke Motor {motor_id}: RPM={rpm_clamped}, Bytes={cmd_bytes_with_crc.hex()}")
            return True
        except serial.SerialException as e:
            logger.error(f"Gagal menulis ke port serial untuk Motor {motor_id}: {e}")
            self.ser = None # Tandai untuk koneksi ulang
            return False

    def set_velocity_mode(self, motor_id: int):
        """Mengatur mode motor ke mode kecepatan (velocity)."""
        logger.info(f"Mengatur Motor {motor_id} ke mode kecepatan (velocity)...")
        cmd_bytes = struct.pack(">BBBBBBBBBB", motor_id, 0xA0, 0, 0, 0, 0, 0, 0, 0, 2)
        cmd_bytes_with_crc = self._crc_attach(cmd_bytes)
        try:
            self.ser.write(cmd_bytes_with_crc)
            time.sleep(0.1) # Beri jeda setelah mengubah mode
        except serial.SerialException as e:
            logger.error(f"Gagal mengatur mode kecepatan untuk Motor {motor_id}: {e}")

# ==============================================================================
# 3. KELAS PEMBACA SENSOR (Diadaptasi dari kode Anda)
# ==============================================================================

class SensorReader:
    """Mengelola komunikasi Modbus ke sensor magnetik."""
    def __init__(self, port, baudrate, address):
        self.client = ModbusSerialClient(
            port=port, baudrate=baudrate, parity='N', stopbits=1, bytesize=8, timeout=0.2
        )
        self.address = address
        self.is_connected = False

    def connect(self):
        """Menyambungkan ke sensor Modbus."""
        if self.is_connected:
            return True
        logger.info("Mencoba terhubung ke sensor...")
        self.is_connected = self.client.connect()
        if self.is_connected:
            logger.info(f"Terhubung ke sensor di {SENSOR_PORT}")
        else:
            logger.error(f"Gagal terhubung ke sensor di {SENSOR_PORT}")
        return self.is_connected

    def close(self):
        """Menutup koneksi sensor."""
        if self.is_connected:
            self.client.close()
            self.is_connected = False
            logger.info("Koneksi sensor ditutup.")

    def read_position(self):
        """Membaca data dari sensor dan mengembalikan posisi median."""
        if not self.is_connected and not self.connect():
            return None

        try:
            result = self.client.read_holding_registers(address=0, count=2, slave=self.address)
            if result.isError():
                logger.warning(f"Error Modbus saat membaca sensor: {result}")
                return None
            
            median_raw = result.registers[0]
            # Faktor pembagi ini mungkin perlu Anda kalibrasi ulang.
            # Tujuannya adalah untuk mendapatkan nilai antara 1.0 dan 16.0
            median_float = median_raw / 235.0 
            
            logger.debug(f"Sensor Raw: {median_raw}, Position: {median_float:.2f}")
            
            if 0 < median_float <= 16:
                return median_float
            else:
                logger.warning(f"Pembacaan sensor di luar jangkauan (1-16): {median_float:.2f}")
                return None

        except Exception as e:
            logger.error(f"Terjadi exception saat membaca sensor: {e}")
            return None

# ==============================================================================
# 4. KELAS KONTROLER PID
# ==============================================================================

class PIDController:
    """Implementasi kontroler PID sederhana."""
    def __init__(self, Kp, Ki, Kd, setpoint):
        self.Kp = Kp
        self.Ki = Ki
        self.Kd = Kd
        self.setpoint = setpoint
        self.last_error = 0
        self.integral = 0
        self.max_integral = MAX_RPM / 2 # Batasi integral untuk mencegah windup

    def update(self, current_value, dt):
        """Menghitung output PID berdasarkan nilai saat ini."""
        # Error dihitung sebagai: setpoint (tujuan) - posisi saat ini
        # Jika posisi < 8.5 (di kanan), error > 0
        # Jika posisi > 8.5 (di kiri), error < 0
        error = self.setpoint - current_value
        
        P = self.Kp * error
        
        self.integral += error * dt
        self.integral = max(-self.max_integral, min(self.integral, self.max_integral))
        I = self.Ki * self.integral
        
        derivative = (error - self.last_error) / dt
        D = self.Kd * derivative
        
        self.last_error = error
        
        output = P + I + D
        return output
    
    def reset(self):
        """Mereset state internal PID."""
        self.last_error = 0
        self.integral = 0
        logger.info("Kontroler PID direset.")

# ==============================================================================
# 5. KELAS UTAMA AGV LINE FOLLOWER
# ==============================================================================

class AGVLineFollower:
    """Kelas utama yang mengorkestrasi sensor, motor, dan logika PID."""
    def __init__(self):
        logger.info("Inisialisasi AGV Line Follower...")
        self.motors = MotorControl(WHEEL_PORT, WHEEL_BAUDRATE)
        self.sensor = SensorReader(SENSOR_PORT, SENSOR_BAUDRATE, SENSOR_ADDRESS)
        self.pid = PIDController(PID_KP, PID_KI, PID_KD, IDEAL_CENTER_SENSOR)
        
        self.line_lost_start_time = None  # Waktu saat pertama kali garis hilang
        self.searching_line = False       # Status apakah sedang mencari garis

        self.running = False
        self.last_line_seen_time = 0

    def setup(self):
        """Menyiapkan koneksi hardware dan mode motor."""
        logger.info("Menjalankan setup hardware...")
        if not self.motors.connect() or not self.sensor.connect():
            logger.error("Gagal melakukan setup awal. Periksa koneksi hardware.")
            return False
        
        self.motors.set_velocity_mode(MOTOR_ID_LEFT)
        self.motors.set_velocity_mode(MOTOR_ID_RIGHT)
        
        self.pid.reset()
        return True

    def stop(self):
        """Menghentikan AGV dan menutup semua koneksi."""
        logger.info("Menghentikan AGV...")
        self.running = False
        logger.info("Mengirim perintah stop ke motor kiri.")
        self.motors.send_rpm(MOTOR_ID_LEFT, 0)
        logger.info("Mengirim perintah stop ke motor kanan.")
        self.motors.send_rpm(MOTOR_ID_RIGHT, 0)
        time.sleep(0.2)
        self.motors.ser.flush()  # Pastikan semua data keluar
        time.sleep(0.2)
        self.motors.close()
        self.sensor.close()
        logger.info("AGV berhasil dihentikan dan koneksi ditutup.")

    def run(self):
        """Loop utama untuk line following menggunakan kontrol PID."""
        if not self.setup():
            return
            
        self.running = True
        logger.info("Memulai loop line following (PID)... Tekan Ctrl+C untuk berhenti.")
        self.last_line_seen_time = time.time()

        while self.running:
            start_time = time.time()
            position = self.sensor.read_position()
            
            if position is not None:
                self.last_line_seen_time = time.time()
                
                correction = self.pid.update(position, LOOP_DELAY_S)
                logger.info(f"Posisi: {position:.2f}, Koreksi PID: {correction:.2f}")

                # =================== PERUBAHAN LOGIKA UTAMA ===================
                # Logika dibalik. Jika error positif (di kanan), kita kurangi kecepatan motor kanan
                # dan tambah kecepatan motor kiri untuk berbelok ke KIRI, dan sebaliknya.
                # Asumsi: Menambah RPM motor kanan dan mengurangi RPM motor kiri akan membuat AGV belok KIRI.
                left_rpm = MAX_RPM + correction 
                right_rpm = MAX_RPM - correction
                # ============================================================
                
                # Batasi kecepatan motor
                left_rpm_final = int(max(0, min(left_rpm, MAX_RPM * 1.5)))
                right_rpm_final = int(max(0, min(right_rpm, MAX_RPM * 1.5)))

                self.motors.send_rpm(MOTOR_ID_LEFT, left_rpm_final)
                self.motors.send_rpm(MOTOR_ID_RIGHT, -right_rpm_final if REVERSE_RIGHT else right_rpm_final)
                kirim_rpm_ke_api(left_rpm_final, right_rpm_final)
                
            else:  # Garis hilang
                current_time = time.time()

                if not self.searching_line:
                    # Mulai mode pencarian
                    self.searching_line = True
                    self.line_lost_start_time = current_time
                    logger.warning("Garis hilang! Memulai pencarian garis kembali...")

                elapsed_search_time = current_time - self.line_lost_start_time

                if elapsed_search_time <= 10.0:
                    # Masih dalam durasi pencarian (maks 5 detik)
                    if self.pid.last_error > 0:
                        # Dulu posisinya di kanan → belok kiri
                        self.motors.send_rpm(MOTOR_ID_LEFT, int(MAX_RPM * 0.5))
                        self.motors.send_rpm(MOTOR_ID_RIGHT, -int(MAX_RPM * 0.5))
                    else:
                        # Dulu posisinya di kiri → belok kanan
                        self.motors.send_rpm(MOTOR_ID_LEFT, -int(MAX_RPM * 0.5))
                        self.motors.send_rpm(MOTOR_ID_RIGHT, int(MAX_RPM * 0.5))
                else:
                    # Waktu pencarian habis → berhenti total
                    logger.error("Gagal menemukan garis dalam 5 detik. Menghentikan AGV.")
                    self.motors.send_rpm(MOTOR_ID_LEFT, 0)
                    self.motors.send_rpm(MOTOR_ID_RIGHT, 0)
                    self.running = False
            
            elapsed_time = time.time() - start_time
            time.sleep(max(0, LOOP_DELAY_S - elapsed_time))

# ==============================================================================
# 6. EKSEKUSI PROGRAM
# ==============================================================================
if __name__ == "__main__":
    agv = AGVLineFollower()
    print("🔥 Line follower dimulai dari subprocess.")
    try:
        # --- PILIH METODE KONTROL DI SINI ---
        
        # Opsi 1: Jalankan dengan kontrol PID yang telah diperbaiki
        agv.run()

    except KeyboardInterrupt:
        logger.info("Program dihentikan oleh pengguna (Ctrl+C).")
    except Exception as e:
        logger.critical(f"Terjadi error fatal: {e}", exc_info=True)
    finally:
        print("🛑 Program line follower selesai.")
        agv.stop()
