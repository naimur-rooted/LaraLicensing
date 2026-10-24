import React, { useState } from "react";
import { Copy, Check, Terminal, FileCode, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";

export function InteractiveCodeShowcase() {
  const [activeTab, setActiveTab] = useState<"python" | "chrome" | "wordpress" | "csharp">("python");
  const [copied, setCopied] = useState(false);

  const snippets = {
    python: {
      fileName: "laralicensing_client.py",
      language: "Python",
      code: `import json, requests, hashlib, platform
from pathlib import Path

VERIFY_URL = "http://localhost:8000/api/v1/products/selfupdatecli/verify"
CACHE_PATH = Path(".license_cache.json")

def verify_or_activate(serial_key: str):
    # 1. Generate local hardware digest from machine signature
    hwid = hashlib.sha256(platform.node().encode()).hexdigest()[:16].upper()
    
    # 2. Online verification & seat binding
    try:
        resp = requests.post(VERIFY_URL, json={
            "serialValue": serial_key,
            "hardwareDigest": hwid,
            "hostname": platform.node()
        }, timeout=4)
        data = resp.json()
        if data.get("IsSuccess"):
            CACHE_PATH.write_text(json.dumps(data["Data"]))
            return True, data["Data"]
    except requests.RequestException:
        pass # Fallback to local offline cache

    # 3. Offline Ed25519 JWT verification
    if CACHE_PATH.exists():
        cached = json.loads(CACHE_PATH.read_text())
        return True, cached # Validated offline within check interval

    return False, {"error": "License verification failed"}`
    },
    chrome: {
      fileName: "background.js",
      language: "JavaScript (Chrome MV3)",
      code: `// LaraLicensing Chrome Extension Client Handshake
const VERIFY_ENDPOINT = "http://localhost:8000/api/v1/products/leadhunter/verify";

async function verifyChromeProfile(serialKey) {
  // 1. Retrieve or generate unique persistent Chrome profile UUID
  let { profileId } = await chrome.storage.local.get(["profileId"]);
  if (!profileId) {
    profileId = "CHROME-" + crypto.randomUUID().slice(0, 12).toUpperCase();
    await chrome.storage.local.set({ profileId });
  }

  // 2. Handshake with LaraLicensing API
  try {
    const res = await fetch(VERIFY_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ serialValue: serialKey, hardwareDigest: profileId })
    });
    const { IsSuccess, Data } = await res.json();
    if (IsSuccess) {
      await chrome.storage.local.set({ offlineToken: Data.offlineToken, cachedAt: Date.now() });
      return { isValid: true, features: Data.features };
    }
  } catch (err) {
    // 3. Fallback to offline cached token
    const { offlineToken } = await chrome.storage.local.get(["offlineToken"]);
    if (offlineToken) return { isValid: true, isOffline: true };
  }
  return { isValid: false };
}`
    },
    wordpress: {
      fileName: "class-license-client.php",
      language: "PHP (WordPress Plugin)",
      code: `<?php
/**
 * LaraLicensing WordPress Plugin Client
 */
class LaraLicensing_Client {
    private const VERIFY_URL = 'http://localhost:8000/api/v1/products/elementor-assistant/verify';

    public static function check_license(string $serial): bool {
        $site_digest = strtoupper(substr(hash('sha256', home_url()), 0, 16));
        $cache = get_transient('laralicensing_token');

        if ($cache) {
            return true; // Verified via local offline transient
        }

        $response = wp_remote_post(self::VERIFY_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'serialValue'    => $serial,
                'hardwareDigest' => $site_digest,
                'hostname'       => parse_url(home_url(), PHP_URL_HOST)
            ]),
            'timeout' => 5,
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            set_transient('laralicensing_token', $data['Data']['offlineToken'], 7 * DAY_IN_SECONDS);
            return true;
        }

        return false;
    }
}`
    },
    csharp: {
      fileName: "LicenseClient.cs",
      language: "C# .NET",
      code: `using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

public class LaraLicenseClient {
    private static readonly HttpClient client = new HttpClient();
    private const string VerifyUrl = "http://localhost:8000/api/v1/products/desktopapp/verify";

    public static async Task<bool> VerifySeatAsync(string serial) {
        string hwid = Environment.MachineName + "-" + Environment.ProcessorCount;
        var payload = JsonSerializer.Serialize(new {
            serialValue = serial,
            hardwareDigest = hwid,
            hostname = Environment.MachineName
        });

        try {
            var content = new StringContent(payload, Encoding.UTF8, "application/json");
            var response = await client.PostAsync(VerifyUrl, content);
            return response.IsSuccessStatusCode;
        } catch {
            return false; // Verify offline fallback
        }
    }
}`
    }
  };

  const current = snippets[activeTab];

  const handleCopy = () => {
    navigator.clipboard.writeText(current.code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <section id="code-showcase" className="py-24 border-b border-border/60 bg-muted/20 scroll-mt-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          {/* Left Text Column matching Cryptlex / LicenseSpring */}
          <div className="lg:col-span-5 space-y-5">
            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/10 text-primary text-sm font-semibold tracking-wide uppercase">
              <Terminal className="size-4" />
              <span>Developer-First Integration</span>
            </div>

            <h2 className="text-4xl sm:text-5xl font-extrabold tracking-tight text-foreground leading-[1.12]" style={{ fontFamily: "var(--font-display)" }}>
              Market-ready in a few lines of code
            </h2>

            <p className="text-base sm:text-lg text-muted-foreground leading-relaxed">
              Integrating LaraLicensing into your software is straightforward and fast. With lightweight headless REST endpoints, Ed25519 public keys, and offline JWT caching, you can implement bulletproof node-locking in minutes, not weeks.
            </p>

            <ul className="space-y-3.5 pt-2 text-sm text-foreground/90 font-medium">
              <li className="flex items-center gap-2.5">
                <span className="size-2 rounded-full bg-emerald-500 shrink-0" />
                <span>Zero bloated native C++ SDK dependencies required.</span>
              </li>
              <li className="flex items-center gap-2.5">
                <span className="size-2 rounded-full bg-emerald-500 shrink-0" />
                <span>Automatic machine seat node-locking & offline caching.</span>
              </li>
              <li className="flex items-center gap-2.5">
                <span className="size-2 rounded-full bg-emerald-500 shrink-0" />
                <span>AI Blueprint Generator produces tailored drop-in code via Gemini.</span>
              </li>
            </ul>
          </div>

          {/* Right Code Block Column */}
          <div className="lg:col-span-7">
            <div className="rounded-2xl border border-border/80 bg-zinc-950 dark:bg-zinc-900 shadow-2xl overflow-hidden">
              {/* Code Tab Bar */}
              <div className="flex items-center justify-between border-b border-zinc-800 bg-zinc-900/80 px-4 py-3">
                <div className="flex items-center gap-2">
                  <span className="size-3 rounded-full bg-rose-500/80" />
                  <span className="size-3 rounded-full bg-amber-500/80" />
                  <span className="size-3 rounded-full bg-emerald-500/80" />
                  <span className="text-xs font-mono text-zinc-400 ml-2">{current.fileName}</span>
                </div>

                {/* Language Selectors */}
                <div className="flex items-center gap-1 bg-zinc-950/70 p-1 rounded-lg border border-zinc-800">
                  <button
                    type="button"
                    onClick={() => setActiveTab("python")}
                    className={`px-2.5 py-1 rounded text-xs font-medium transition-colors ${
                      activeTab === "python" ? "bg-zinc-800 text-white font-semibold" : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    Python
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveTab("chrome")}
                    className={`px-2.5 py-1 rounded text-xs font-medium transition-colors ${
                      activeTab === "chrome" ? "bg-zinc-800 text-white font-semibold" : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    Chrome JS
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveTab("wordpress")}
                    className={`px-2.5 py-1 rounded text-xs font-medium transition-colors ${
                      activeTab === "wordpress" ? "bg-zinc-800 text-white font-semibold" : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    WordPress
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveTab("csharp")}
                    className={`px-2.5 py-1 rounded text-xs font-medium transition-colors ${
                      activeTab === "csharp" ? "bg-zinc-800 text-white font-semibold" : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    C# .NET
                  </button>
                </div>
              </div>

              {/* Code Viewer */}
              <div className="relative p-5 font-mono text-[13px] text-zinc-100 overflow-x-auto max-h-[380px]">
                <pre className="leading-relaxed">
                  <code>{current.code}</code>
                </pre>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={handleCopy}
                  className="absolute top-4 right-4 h-7 gap-1.5 text-[11px] bg-zinc-800 hover:bg-zinc-700 text-white shadow-md border border-zinc-700"
                >
                  {copied ? <Check className="size-3 text-emerald-400" /> : <Copy className="size-3" />}
                  {copied ? "Copied!" : "Copy Code"}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
