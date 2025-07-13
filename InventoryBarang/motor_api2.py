from flask import Flask, jsonify, request
from ddsm115 import MotorControl
import time
import subprocess
import os
import signal
import pymysql


app = Flask(__name__)

linefollower_process = None


@app.route('/log_rpm', methods=['POST'])
def log_rpm():
    try:
        rpm1 = int(request.json.get("rpm1", 0))
        rpm2 = int(request.json.get("rpm2", 0))

        # Koneksi database (ganti sesuai konfigurasimu)
        conn = pymysql.connect(
            host='localhost',
            user='root',
            password='',
            db='InventoryBarang'
        )
        cursor = conn.cursor()

        # Simpan ke tabel rpm_log tanpa kolom 'sumber'
        cursor.execute(
            "INSERT INTO rpm_log (rpm1, rpm2, waktu) VALUES (%s, %s, NOW())",
            (rpm1, rpm2)
        )

        conn.commit()
        conn.close()
        return jsonify({"status": "saved"})
    except Exception as e:
        print(f"❌ Gagal log RPM:", str(e))
        return jsonify({"error": str(e)}), 500



@app.route('/run_motor', methods=['POST'])
def run_motor():
    try:
        rpm1 = int(request.json.get("rpm1", 0))
        rpm2 = int(request.json.get("rpm2", 0))

        print(f"⚙️ Menjalankan run_motor dengan rpm1={rpm1}, rpm2={rpm2}")

        motor = MotorControl()
        motor.set_drive_mode(1, 2)
        motor.set_drive_mode(2, 2)

        motor.send_rpm(2, rpm1)
        motor.send_rpm(1, -1 * rpm2)

        return jsonify({
            "status": "running",
            "rpm1": rpm1,
            "rpm2": rpm2
        })

    except Exception as e:
        print(f"❌ ERROR:", str(e))
        return jsonify({"error": str(e)}), 500


@app.route('/start_linefollower', methods=['POST'])
def start_linefollower():
    global linefollower_process
    if linefollower_process is None or linefollower_process.poll() is not None:
        try:
            print("🚀 Memulai subprocess linefollow.py")
            linefollower_process = subprocess.Popen(["python", "linefollow.py"])
            return jsonify({"status": "Line follower started."})
        except Exception as e:
            print("❌ Gagal menjalankan subprocess:", e)
            return jsonify({"status": "error", "message": str(e)}), 500
    else:
        return jsonify({"status": "Line follower already running."})

@app.route('/stop_linefollower', methods=['POST'])
def stop_linefollower():
    global linefollower_process
    if linefollower_process and linefollower_process.poll() is None:
        print("🛑 Menghentikan subprocess linefollow.py")
        linefollower_process.terminate()
        linefollower_process.wait(timeout=2)  # Tunggu proses selesai
        linefollower_process = None

        try:
            # 💡 Langsung kontrol motor untuk menghentikan setelah proses mati
            from ddsm115 import MotorControl
            motor = MotorControl(device="COM29")
            motor.set_drive_mode(1, 2)
            motor.set_drive_mode(2, 2)
            motor.send_rpm(1, 0)
            motor.send_rpm(2, 0)
            print("✅ Motor berhasil dihentikan setelah proses dimatikan.")
        except Exception as e:
            print("⚠️ Gagal menghentikan motor:", e)

        return jsonify({"status": "Line follower stopped and motor halted."})
    else:
        return jsonify({"status": "No line follower running."})

    
if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=True)
