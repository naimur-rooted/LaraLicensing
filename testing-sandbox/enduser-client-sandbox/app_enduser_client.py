import sys
import os
import json
import uuid
import hashlib
import platform
import socket
import datetime
import urllib.request
import urllib.error
import tkinter as tk
from tkinter import ttk, messagebox

SERVER_URL = "http://127.0.0.1:8000"
CACHE_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".enduser_license_cache.json")

def get_machine_fingerprint():
    host = socket.gethostname()
    os_name = platform.system()
    mac = uuid.getnode()
    raw = f"{host}|{os_name}|{mac}"
    digest = hashlib.sha256(raw.encode("utf-8")).hexdigest()[:16].upper()
    return {
        "HardwareDigest": digest,
        "Hostname": host,
        "Os": os_name,
        "Raw": raw
    }

class EndUserClientApp:
    def __init__(self, root):
        self.root = root
        self.root.title("LeadScraper Pro Client - End User Simulator (App B)")
        self.root.geometry("780x640")
        self.root.configure(bg="#0b0f19")
        self.hw = get_machine_fingerprint()

        self.product_slug = tk.StringVar(value="lara-sandbox-desktop-client")
        self.serial_var = tk.StringVar(value="")
        self.simulated_hwid = tk.StringVar(value=self.hw["HardwareDigest"])

        self.cached_license = self.load_cache()

        self.setup_ui()
        self.render_status()

    def setup_ui(self):
        style = ttk.Style()
        style.theme_use("clam")

        # Header
        header = tk.Frame(self.root, bg="#111827", padx=20, pady=16, bd=1, relief="solid")
        header.pack(fill="x", padx=16, pady=10)

        title = tk.Label(header, text="LeadScraper Pro Client (Protected Software)", font=("Segoe UI", 15, "bold"), fg="#38bdf8", bg="#111827")
        title.pack(anchor="w")
        sub = tk.Label(header, text="Autonomous Client License Activation & Multi-Seat Verification Engine", font=("Segoe UI", 9), fg="#9ca3af", bg="#111827")
        sub.pack(anchor="w")

        # Hardware & Machine Card
        hw_card = tk.LabelFrame(self.root, text="  Device Machine Fingerprint (Anti-Piracy HWID)  ", font=("Segoe UI", 9, "bold"), fg="#34d399", bg="#111827", padx=14, pady=10)
        hw_card.pack(fill="x", padx=16, pady=6)

        hw_text = f"Primary Host: {self.hw['Hostname']}  |  OS: {self.hw['Os']}  |  Native HWID: {self.hw['HardwareDigest']}"
        tk.Label(hw_card, text=hw_text, font=("Consolas", 9), fg="#d1d5db", bg="#111827").pack(anchor="w")

        # HWID Simulation Switcher (For testing multi-seat overflow & impossible travel)
        sim_row = tk.Frame(hw_card, bg="#111827")
        sim_row.pack(fill="x", pady=6)
        tk.Label(sim_row, text="Simulate Different Machine HWID:", font=("Segoe UI", 8), fg="#9ca3af", bg="#111827").pack(side="left")
        hw_entry = tk.Entry(sim_row, textvariable=self.simulated_hwid, font=("Consolas", 9, "bold"), bg="#030712", fg="#fbbf24", width=24)
        hw_entry.pack(side="left", padx=8)

        tk.Button(sim_row, text="Generate New PC HWID", font=("Segoe UI", 8), bg="#374151", fg="white", command=self.generate_random_hwid).pack(side="left")
        tk.Button(sim_row, text="Reset to Native", font=("Segoe UI", 8), bg="#1f2937", fg="#9ca3af", command=self.reset_native_hwid).pack(side="left", padx=4)

        # License Activation Card
        act_card = tk.LabelFrame(self.root, text="  License Key Activation  ", font=("Segoe UI", 9, "bold"), fg="#38bdf8", bg="#111827", padx=14, pady=12)
        act_card.pack(fill="x", padx=16, pady=6)

        row1 = tk.Frame(act_card, bg="#111827")
        row1.pack(fill="x", pady=4)
        tk.Label(row1, text="Product Slug:", font=("Segoe UI", 9, "bold"), fg="#f3f4f6", bg="#111827", width=14, anchor="w").pack(side="left")
        tk.Entry(row1, textvariable=self.product_slug, font=("Consolas", 9), bg="#030712", fg="#38bdf8", width=30).pack(side="left")

        row2 = tk.Frame(act_card, bg="#111827")
        row2.pack(fill="x", pady=4)
        tk.Label(row2, text="Serial Key:", font=("Segoe UI", 9, "bold"), fg="#f3f4f6", bg="#111827", width=14, anchor="w").pack(side="left")
        tk.Entry(row2, textvariable=self.serial_var, font=("Consolas", 10, "bold"), bg="#030712", fg="#fbbf24", width=40).pack(side="left")

        btn_row = tk.Frame(act_card, bg="#111827")
        btn_row.pack(fill="x", pady=8)
        tk.Button(btn_row, text="⚡ Verify & Bind License", font=("Segoe UI", 9, "bold"), bg="#10b981", fg="#000", padx=14, pady=4, command=self.verify_and_activate).pack(side="left")
        tk.Button(btn_row, text="🗑 Clear Cache & Deactivate", font=("Segoe UI", 9), bg="#dc2626", fg="white", padx=10, pady=4, command=self.clear_cache_action).pack(side="left", padx=10)

        # Status & Offline Token Display
        status_card = tk.LabelFrame(self.root, text="  Software Authorization Status & Ed25519 Token  ", font=("Segoe UI", 9, "bold"), fg="#a78bfa", bg="#111827", padx=14, pady=10)
        status_card.pack(fill="both", expand=True, padx=16, pady=8)

        self.status_lbl = tk.Label(status_card, text="NOT ACTIVATED", font=("Segoe UI", 12, "bold"), fg="#ef4444", bg="#111827")
        self.status_lbl.pack(anchor="w", pady=4)

        self.details_lbl = tk.Label(status_card, text="Please enter a valid serial key issued from App A Storefront.", font=("Consolas", 9), fg="#9ca3af", bg="#111827", justify="left")
        self.details_lbl.pack(anchor="w")

        tk.Label(status_card, text="Asymmetric Ed25519 Offline Token (JWT):", font=("Segoe UI", 8, "bold"), fg="#6ee7b7", bg="#111827").pack(anchor="w", pady=(8, 2))
        self.token_txt = tk.Text(status_card, height=4, font=("Consolas", 8), bg="#030712", fg="#6ee7b7", wrap="char")
        self.token_txt.pack(fill="x")

    def generate_random_hwid(self):
        new_hw = "HWID_" + hashlib.md5(str(uuid.uuid4()).encode()).hexdigest()[:12].upper()
        self.simulated_hwid.set(new_hw)

    def reset_native_hwid(self):
        self.simulated_hwid.set(self.hw["HardwareDigest"])

    def verify_and_activate(self):
        serial = self.serial_var.get().strip()
        slug = self.product_slug.get().strip()
        hwid = self.simulated_hwid.get().strip()

        if not serial:
            messagebox.showwarning("Input Required", "Please paste a Serial Key to activate.")
            return

        url = f"{SERVER_URL}/api/v1/products/{slug}/verify"
        payload = {
            "serialValue": serial,
            "hardwareDigest": hwid,
            "hostname": self.hw["Hostname"]
        }

        try:
            req = urllib.request.Request(
                url,
                data=json.dumps(payload).encode("utf-8"),
                headers={"Content-Type": "application/json"}
            )
            with urllib.request.urlopen(req, timeout=4) as resp:
                data = json.loads(resp.read().decode())
                if data.get("IsSuccess"):
                    res = data["Data"]
                    self.cached_license = {
                        "serialValue": serial,
                        "productSlug": slug,
                        "dealCode": res.get("dealCode"),
                        "allowedSeats": res.get("allowedSeats"),
                        "activeSeats": res.get("activeSeats"),
                        "checkIntervalDays": res.get("checkIntervalDays"),
                        "nextCheckBefore": res.get("nextCheckBefore"),
                        "offlineToken": res.get("offlineToken"),
                        "hardwareDigest": hwid,
                        "verifiedAt": datetime.datetime.now().isoformat()
                    }
                    self.save_cache(self.cached_license)
                    self.render_status()
                    messagebox.showinfo("Success", f"License Verified & Activated!\nHardware Bound: {hwid}\nSeats: {res.get('activeSeats')}/{res.get('allowedSeats')}")
        except urllib.error.HTTPError as e:
            try:
                err_data = json.loads(e.read().decode())
                err_code = err_data.get("Data", {}).get("errorCode") or err_data.get("ErrorCode") or "Error"
                err_msg = err_data.get("Data", {}).get("message") or err_data.get("Message") or "Activation failed"
                messagebox.showerror(f"Activation Blocked ({err_code})", f"{err_msg}\n\nHTTP {e.code}")
            except Exception:
                messagebox.showerror("Server Error", f"HTTP {e.code}: {e.reason}")
        except Exception as ex:
            messagebox.showerror("Connection Failed", f"Could not connect to {SERVER_URL}: {ex}")

    def render_status(self):
        if self.cached_license:
            self.status_lbl.config(text="✔ ACTIVATED & GENUINE (LICENSED)", fg="#10b981")
            info = (
                f"Serial: {self.cached_license.get('serialValue')}\n"
                f"Product: {self.cached_license.get('productSlug')}  |  Deal: {self.cached_license.get('dealCode')}\n"
                f"Machine Seats: {self.cached_license.get('activeSeats')} / {self.cached_license.get('allowedSeats')} Active\n"
                f"Bound HWID: {self.cached_license.get('hardwareDigest')}\n"
                f"Next Cadence Re-check: {self.cached_license.get('nextCheckBefore', 'N/A')}"
            )
            self.details_lbl.config(text=info, fg="#f3f4f6")
            self.token_txt.delete("1.0", tk.END)
            self.token_txt.insert(tk.END, self.cached_license.get("offlineToken", "N/A"))
            self.serial_var.set(self.cached_license.get("serialValue", ""))
        else:
            self.status_lbl.config(text="✖ NOT ACTIVATED", fg="#ef4444")
            self.details_lbl.config(text="Enter serial key and click 'Verify & Bind License'.", fg="#9ca3af")
            self.token_txt.delete("1.0", tk.END)

    def load_cache(self):
        if os.path.exists(CACHE_FILE):
            try:
                with open(CACHE_FILE, "r", encoding="utf-8") as f:
                    return json.load(f)
            except Exception:
                return None
        return None

    def save_cache(self, data):
        try:
            with open(CACHE_FILE, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2)
        except Exception:
            pass

    def clear_cache_action(self):
        if os.path.exists(CACHE_FILE):
            os.remove(CACHE_FILE)
        self.cached_license = None
        self.render_status()
        messagebox.showinfo("Cache Cleared", "Local license token cleared. Software is now deactivated.")

def main():
    root = tk.Tk()
    app = EndUserClientApp(root)
    root.mainloop()

if __name__ == "__main__":
    main()
