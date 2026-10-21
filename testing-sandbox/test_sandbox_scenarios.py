import sys
import os
import json
import urllib.request
import urllib.error

API_BASE = "http://127.0.0.1:8000"

def run_tests():
    print("=" * 70)
    print("  RUNNING REAL SCENARIO DUAL-SANDBOX VERIFICATION TEST")
    print("=" * 70)
    passed = 0
    total = 5

    # 1. Method 1 Headless Deals Synchronization Test
    print("\n[TEST 1] Testing Method 1 Headless Deals API Sync...")
    try:
        url = f"{API_BASE}/api/v1/products/elementor-assistant/deals"
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode())
            assert data["IsSuccess"] is True
            deals = data["Data"]["deals"]
            assert len(deals) >= 3
            print(f"       -> PASS: Synced {len(deals)} live deals from Shard (product_elementor-assistant.sqlite)")
            passed += 1
    except Exception as e:
        print(f"       -> FAIL: {e}")

    # 2. Decoupled Payment Webhook License Issuance Test
    print("\n[TEST 2] Testing Decoupled Stripe Webhook Instant License Minting...")
    serial_issued = None
    try:
        url = f"{API_BASE}/api/v1/products/elementor-assistant/licenses/issue"
        payload = {
            "transactionId": f"tx_test_real_scenario_{os.urandom(4).hex()}",
            "customerEmail": "real.customer@testing.local",
            "dealCode": "ELM_1Y_THREE"
        }
        req = urllib.request.Request(url, data=json.dumps(payload).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode())
            assert data["IsSuccess"] is True
            serial_issued = data["Data"]["SerialValue"]
            assert "ELEMENTOR-ASSISTANT" in serial_issued
            print(f"       -> PASS: Minted Serial Key: {serial_issued}")
            passed += 1
    except Exception as e:
        print(f"       -> FAIL: {e}")

    # 3. App B Client Handshake & Ed25519 Offline Token Issuance
    print("\n[TEST 3] Testing App B Client Activation & Hardware Seat Binding...")
    try:
        url = f"{API_BASE}/api/v1/products/elementor-assistant/verify"
        payload = {
            "serialValue": serial_issued or "ELEMENTOR-ASSISTANT-DEMO",
            "hardwareDigest": "HWID_TEST_MACHINE_ALPHA",
            "hostname": "Developer-PC"
        }
        req = urllib.request.Request(url, data=json.dumps(payload).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = json.loads(resp.read().decode())
            assert data["IsSuccess"] is True
            res = data["Data"]
            assert res["isValid"] is True
            assert res["activeSeats"] == 1
            assert len(res["offlineToken"]) > 20
            print(f"       -> PASS: Bound to HWID_TEST_MACHINE_ALPHA, Offline JWT Token received.")
            passed += 1
    except Exception as e:
        print(f"       -> FAIL: {e}")

    # 4. Seat Limit Enforcement Test (Multi-Seat Overflow)
    print("\n[TEST 4] Testing Single Seat Limit Overflow Protection...")
    try:
        # Mint a fresh 1-seat license for isolated test
        mint_url = f"{API_BASE}/api/v1/products/elementor-assistant/licenses/issue"
        mint_payload = {
            "transactionId": f"tx_single_seat_{os.urandom(4).hex()}",
            "customerEmail": "single.user@testing.local",
            "dealCode": "ELM_1M_SINGLE"
        }
        mint_req = urllib.request.Request(mint_url, data=json.dumps(mint_payload).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(mint_req, timeout=10) as mint_resp:
            mint_data = json.loads(mint_resp.read().decode())
            single_seat_serial = mint_data["Data"]["SerialValue"]

        # Machine 1 binds successfully
        url = f"{API_BASE}/api/v1/products/elementor-assistant/verify"
        req1 = urllib.request.Request(url, data=json.dumps({
            "serialValue": single_seat_serial,
            "hardwareDigest": "HWID_FIRST_PC"
        }).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req1, timeout=10) as resp:
            pass

        # Machine 2 attempts to use same single seat key -> must be blocked
        req2 = urllib.request.Request(url, data=json.dumps({
            "serialValue": single_seat_serial,
            "hardwareDigest": "HWID_SECOND_PIRATED_PC"
        }).encode(), headers={"Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(req2, timeout=10) as resp2:
                print("       -> FAIL: Second machine was not blocked!")
        except urllib.error.HTTPError as he:
            assert he.code == 403
            err = json.loads(he.read().decode())
            assert err["Data"]["errorCode"] == "SeatLimitExceeded"
            print(f"       -> PASS: Correctly blocked with HTTP 403 SeatLimitExceeded.")
            passed += 1
    except Exception as e:
        print(f"       -> FAIL: {e}")

    # 5. Fraud Guard 1-Click Quarantine Test
    print("\n[TEST 5] Testing Real-Time Fraud Guard 1-Click Quarantine Action...")
    try:
        q_url = f"{API_BASE}/api/v1/security/elementor-assistant/quarantine"
        q_req = urllib.request.Request(q_url, data=json.dumps({
            "serialValue": single_seat_serial,
            "reason": "Piracy attempt on multiple unauthorized devices"
        }).encode(), headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(q_req, timeout=10) as q_resp:
            q_data = json.loads(q_resp.read().decode())
            assert q_data["IsSuccess"] is True

        # Now Machine 1 verification must also fail because license was revoked
        v_url = f"{API_BASE}/api/v1/products/elementor-assistant/verify"
        v_req = urllib.request.Request(v_url, data=json.dumps({
            "serialValue": single_seat_serial,
            "hardwareDigest": "HWID_FIRST_PC"
        }).encode(), headers={"Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(v_req, timeout=10) as v_resp:
                print("       -> FAIL: Quarantined license was still allowed!")
        except urllib.error.HTTPError as he2:
            assert he2.code == 403
            err2 = json.loads(he2.read().decode())
            assert err2["Data"]["errorCode"] == "LicenseRevoked"
            print("       -> PASS: Quarantined license immediately revoked on server.")
            passed += 1
    except Exception as e:
        print(f"       -> FAIL: {e}")

    print("\n" + "=" * 70)
    print(f"  TOTAL SCENARIO TESTS: {total} | PASSED: {passed} | FAILED: {total - passed}")
    print("=" * 70)
    return 0 if passed == total else 1

if __name__ == "__main__":
    sys.exit(run_tests())
