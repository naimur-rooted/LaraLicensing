# Code signing and thin client guidance for LaraLicensing developers

This doc is guidance for developers shipping an app that uses
LaraLicensing. It is not a security guarantee on its own. The
real enforcement is server-side: signed release manifests,
licence tokens, seat limits, heartbeat, and fraud scoring.

## Ship

- Sign desktop builds with OS code signing, plus timestamping,
  so a signature stays valid after a certificate expires.
  On Windows, Authenticode. On macOS, notarization.
- Publish the signed release manifest hash with the release,
  so a customer can verify the build they received.
- Write a short SECURITY note for enterprise reviewers: what
  you sign with, what you deliberately do not do, and how a
  customer verifies a build.

## Do not ship

- Do not use UPX or heavy packers, or aggressive anti-debug
  traps. They cause antivirus quarantine and enterprise review
  failures, and they only delay a determined attacker.
- Do not ship a product private key, webhook secret, licence
  private key, or any other secret in client code, including
  JavaScript. Only a public key may be embedded.
- Do not ship deceptive strings meant to trick an AI
  decompiler. They are strippable, they can trigger review
  problems, and they are not security. Server logic is the
  defence, not a hidden string.
- Do not rely on a client-side licence check alone. A patched
  client can skip that check. The server must gate premium
  features, seats, expiry, and revocation.

## Extension thin client rules

- Chrome MV3 forbids remote code, and the store bans
  obfuscation. Treat an extension as a thin client: no secrets,
  no proprietary logic, no licence private key in JavaScript.
- Use the narrowest permissions, strict CSP with
  script-src self and object-src self, and no eval.
- Verify the licence token on the server on every API call.
  A patched unpacked extension with a stolen token looks
  identical to a legitimate one until heartbeat and fraud
  signals catch it, which is why server counting matters.
- Use Web Store verified uploads, so a compromised dashboard
  account alone cannot push a malicious update.

## Client boot checklist

1. Verify the licence token signature, expiry, product, and fingerprint.
2. Verify the signed release manifest signature with the pinned public key.
3. Re-hash licence-critical files and compare with the manifest.
4. Check the release version against the server minVersion floor.
5. On any mismatch, fail closed: premium features off, no partial unlock.
6. Report a tamper event to tamper-verify, for developer review only.
   A tamper report alone never auto-revokes a licence.
