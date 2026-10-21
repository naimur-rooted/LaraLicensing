import http.server
import socketserver
import json
import urllib.request
import urllib.error

PORT = 5050
LARALICENSING_API = "http://127.0.0.1:8000"
DEFAULT_PRODUCT_SLUG = "lara-sandbox-desktop-client"

HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Developer Storefront Sandbox (Method 1 Live Sync)</title>
    <style>
        :root {
            --bg-dark: #090d16;
            --card-bg: #111827;
            --primary: #38bdf8;
            --accent: #10b981;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border: #1f2937;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg-dark); color: var(--text-main); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 35px; }
        h1 { font-size: 26px; color: #fff; margin-bottom: 8px; }
        p.subtitle { color: var(--text-muted); font-size: 14px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .badge-live { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .control-panel { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
        .input-group { display: flex; align-items: center; gap: 10px; font-size: 13px; }
        input[type="text"] { background: #030712; border: 1px solid var(--border); color: #fff; padding: 8px 12px; border-radius: 6px; font-size: 13px; outline: none; min-width: 280px; font-family: monospace; }
        button { background: var(--primary); color: #000; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; transition: opacity 0.2s; }
        button:hover { opacity: 0.9; }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .pricing-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; position: relative; transition: transform 0.2s, border-color 0.2s; }
        .pricing-card:hover { transform: translateY(-3px); border-color: #374151; }
        .pricing-card.decoy-target { border: 2px solid var(--accent); background: linear-gradient(180deg, rgba(16, 185, 129, 0.04) 0%, var(--card-bg) 100%); }
        .decoy-ribbon { position: absolute; top: -12px; right: 20px; background: var(--accent); color: #000; font-size: 10px; font-weight: 800; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; }
        .deal-name { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 6px; }
        .deal-code { font-size: 11px; font-family: monospace; color: var(--text-muted); margin-bottom: 16px; }
        .price-box { margin-bottom: 20px; }
        .price { font-size: 32px; font-weight: 800; color: #fff; }
        .interval { color: var(--text-muted); font-size: 13px; }
        .features-list { list-style: none; margin-bottom: 24px; font-size: 13px; color: #d1d5db; space-y: 8px; }
        .features-list li { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .features-list li::before { content: "✓"; color: var(--accent); font-weight: bold; }
        .btn-buy { width: 100%; padding: 12px; font-size: 14px; text-align: center; border-radius: 8px; }
        .btn-primary { background: var(--accent); color: #000; }
        .btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .console-box { background: #030712; border: 1px solid var(--border); border-radius: 12px; padding: 20px; font-family: monospace; font-size: 12px; }
        .console-title { color: var(--primary); font-weight: bold; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        pre { white-space: pre-wrap; word-break: break-all; color: #a7f3d0; max-height: 220px; overflow-y: auto; }
        .status-pill { padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <span class="badge badge-live">Method 1 Live Headless Synchronization</span>
            <h1>Developer Storefront Sandbox</h1>
            <p class="subtitle">Simulating developer's marketing website querying LaraLicensing live REST API</p>
        </header>

        <div class="control-panel">
            <div class="input-group">
                <span>Product Slug:</span>
                <input type="text" id="productSlugInput" value="lara-sandbox-desktop-client">
                <button onclick="fetchPricing()">Re-Sync Deals</button>
            </div>
            <div class="input-group">
                <span id="syncStatus" style="color: #9ca3af; font-size: 12px;">Connected to http://127.0.0.1:8000</span>
            </div>
        </div>

        <div id="pricingGrid" class="cards-grid">
            <!-- Dynamic deals loaded here via Method 1 -->
            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px;">
                Loading live pricing deals from LaraLicensing...
            </div>
        </div>

        <div class="console-box">
            <div class="console-title">
                <span>📡 Simulation Webhook & License Generation Console</span>
                <span id="webhookStatus">Ready</span>
            </div>
            <pre id="consoleOutput">// Click any "Simulate Purchase" button above to test instant decoupled webhook license issuance...</pre>
        </div>
    </div>

    <script>
        function setSlug(s) {
            document.getElementById('productSlugInput').value = s;
            fetchPricing();
        }

        async function fetchPricing() {
            const slug = document.getElementById('productSlugInput').value.trim();
            const grid = document.getElementById('pricingGrid');
            const status = document.getElementById('syncStatus');
            if (!slug) {
                status.innerHTML = '<span style="color:#ef4444;">Please enter a product slug.</span>';
                return;
            }
            status.innerHTML = "Fetching from /api/v1/products/" + slug + "/deals...";
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px;">Querying LaraLicensing for ' + slug + '...</div>';

            try {
                const res = await fetch('/api/proxy-deals?slug=' + encodeURIComponent(slug));
                let data;
                try {
                    data = await res.json();
                } catch (e) {
                    data = { IsSuccess: false, Message: "Server returned non-JSON response" };
                }

                if (!res.ok || !data.IsSuccess || !data.Data) {
                    const msg = (data && data.Message) ? data.Message : "Product not found";
                    grid.innerHTML = '<div style="grid-column:1/-1; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:10px; padding:24px; color:#fca5a5; text-align:center;">' +
                        '<div style="font-size:16px; font-weight:700; margin-bottom:6px;">Product Not Found: ' + slug + '</div>' +
                        '<p style="font-size:13px; color:#9ca3af; max-width:550px; margin:0 auto 12px;">' + msg + '. Verify your product is registered in the Developer Portal.</p>' +
                        '</div>';
                    status.innerHTML = '<span style="color:#ef4444;">Error: ' + msg + '</span>';
                    return;
                }

                status.innerHTML = '<span style="color:#10b981;">● Synced live with Shard: product_' + slug + '.sqlite</span>';
                const deals = data.Data.deals || [];
                if (deals.length === 0) {
                    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:30px; color:#9ca3af;">No active pricing deals configured for this product.</div>';
                    return;
                }

                grid.innerHTML = deals.map(d => {
                    const priceFormatted = (d.priceCents / 100).toFixed(2);
                    const isDecoy = d.isDecoyTarget;
                    const featuresHtml = (d.features && Array.isArray(d.features) && d.features.length > 0)
                        ? d.features.map(f => `<li><strong>${f}</strong></li>`).join('')
                        : `
                            <li>Hardware Seats: <strong>${d.allowedMachineSeats} Allowed Machine(s)</strong></li>
                            <li>Allowed Domains: <strong>${d.allowedDomains} Domain(s)</strong></li>
                            <li>Cadence Check: <strong>Every ${data.Data.checkIntervalDays || 7} Days</strong></li>
                            <li>Offline Verification: <strong>Signed Ed25519 JWT</strong></li>
                        `;
                    return `
                        <div class="pricing-card ${isDecoy ? 'decoy-target' : ''}">
                            ${isDecoy ? '<div class="decoy-ribbon">⭐ BEST VALUE (DECOY TARGET)</div>' : ''}
                            <div>
                                <div class="deal-name">${d.dealName}</div>
                                <div class="deal-code">DEAL CODE: ${d.dealCode}</div>
                                <div class="price-box">
                                    <span class="price">$${priceFormatted}</span>
                                    <span class="interval">/ ${d.billingInterval}</span>
                                </div>
                                <ul class="features-list">
                                    ${featuresHtml}
                                </ul>
                            </div>
                            <button class="btn-buy ${isDecoy ? 'btn-primary' : 'btn-outline'}" onclick="simulatePurchase('${slug}', '${d.dealCode}')">
                                Simulate Stripe Purchase ($${priceFormatted})
                            </button>
                        </div>
                    `;
                }).join('');

            } catch (err) {
                grid.innerHTML = '<div style="grid-column:1/-1; color:#ef4444; text-align:center;">Failed to connect to backend server: ' + err.message + '</div>';
            }
        }

        async function simulatePurchase(productSlug, dealCode) {
            const consoleOut = document.getElementById('consoleOutput');
            const status = document.getElementById('webhookStatus');
            const randomEmail = 'customer.' + Math.floor(Math.random() * 8999 + 1000) + '@acmecorp.local';
            const fakeTxId = 'ch_stripe_' + Math.random().toString(36).substring(2, 12);

            status.innerHTML = '<span style="color:#f59e0b;">Dispatching payment webhook...</span>';
            consoleOut.textContent = `Dispatched Webhook Payload:\n` + JSON.stringify({
                transactionId: fakeTxId,
                customerEmail: randomEmail,
                dealCode: dealCode,
                gateway: "Stripe_Checkout_Mock"
            }, null, 2);

            try {
                const res = await fetch('/api/proxy-webhook', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        slug: productSlug,
                        transactionId: fakeTxId,
                        customerEmail: randomEmail,
                        dealCode: dealCode
                    })
                });

                const data = await res.json();
                status.innerHTML = '<span style="color:#10b981;">✔ Serial Minted Successfully</span>';
                consoleOut.textContent = `LaraLicensing Webhook Response (Instant Serial Minted):\n\n` +
                    JSON.stringify(data, null, 2) +
                    `\n\n👉 COPY THIS SERIAL KEY TO TEST IN APP B (End-User Simulator):\n` +
                    `Serial Key: ` + (data.Data?.SerialValue || "N/A");
            } catch (err) {
                status.innerHTML = '<span style="color:#ef4444;">✖ Webhook Failed</span>';
                consoleOut.textContent = "Error: " + err.message;
            }
        }

        // Initial fetch
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fetchPricing);
        } else {
            fetchPricing();
        }
    </script>
</body>
</html>
"""

class SandboxHandler(http.server.SimpleHTTPRequestHandler):
    def end_headers(self):
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
        self.send_header("Pragma", "no-cache")
        self.send_header("Expires", "0")
        super().end_headers()

    def do_GET(self):
        clean_path = self.path.split("?")[0]
        if clean_path in ("/", "/index.html"):
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
            self.wfile.write(HTML_TEMPLATE.encode("utf-8"))
        elif clean_path.startswith("/api/proxy-deals"):
            # Proxy request to backend
            slug = self.path.split("slug=")[-1].strip() if "slug=" in self.path else DEFAULT_PRODUCT_SLUG
            url = f"{LARALICENSING_API}/api/v1/products/{slug}/deals"
            try:
                with urllib.request.urlopen(url, timeout=5) as resp:
                    data = resp.read()
                    self.send_response(resp.status)
                    self.send_header("Content-Type", "application/json")
                    self.end_headers()
                    self.wfile.write(data)
            except urllib.error.HTTPError as he:
                err_data = he.read()
                self.send_response(he.code)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(err_data if err_data else json.dumps({"IsSuccess": False, "Message": f"HTTP Error {he.code}"}).encode())
            except Exception as e:
                self.send_response(500)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({"IsSuccess": False, "Message": str(e)}).encode())
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        clean_path = self.path.split("?")[0]
        if clean_path == "/api/proxy-webhook":
            content_len = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_len).decode("utf-8")
            payload = json.loads(body)
            slug = payload.get("slug", DEFAULT_PRODUCT_SLUG)

            url = f"{LARALICENSING_API}/api/v1/products/{slug}/licenses/issue"
            try:
                req = urllib.request.Request(
                    url,
                    data=json.dumps(payload).encode("utf-8"),
                    headers={"Content-Type": "application/json"}
                )
                with urllib.request.urlopen(req, timeout=5) as resp:
                    data = resp.read()
                    self.send_response(resp.status)
                    self.send_header("Content-Type", "application/json")
                    self.end_headers()
                    self.wfile.write(data)
            except urllib.error.HTTPError as he:
                err_data = he.read()
                self.send_response(he.code)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(err_data if err_data else json.dumps({"IsSuccess": False, "Message": f"HTTP Error {he.code}"}).encode())
            except Exception as e:
                self.send_response(500)
                self.send_header("Content-Type", "application/json")
                self.end_headers()
                self.wfile.write(json.dumps({"IsSuccess": False, "Message": str(e)}).encode())

class ThreadingHTTPServer(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True

def run_server():
    server = ThreadingHTTPServer(("", PORT), SandboxHandler)
    print(f"==================================================================")
    print(f"  APP A: DEVELOPER STOREFRONT SANDBOX (Method 1 Live Sync)")
    print(f"  Running at: http://localhost:{PORT}")
    print(f"==================================================================")
    server.serve_forever()

if __name__ == "__main__":
    run_server()
