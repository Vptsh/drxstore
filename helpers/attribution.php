<?php
/**
 * DRXStore v2.0.0 - Attribution Protection System
 * Developed by Vineet | psvineet@zohomail.in
 *
 * Multi-layer mechanism:
 * Layer 1: Runtime integrity hash stored in data/
 * Layer 2: PHP source hash verification
 * Layer 3: DB-level marker checked on every request
 * Layer 4: Obfuscated constant embedded in CSS output
 * Layer 5: Renders a warning banner if tampered
 *
 * DO NOT MODIFY THIS FILE.
 */

define('_AUTHOR_RAW',  'Vineet');
define('_AUTHOR_EMAIL','psvineet@zohomail.in');
define('_BUILD_SIG',   'DRXStore-v2.0.0-by-Vineet');

// Canonical strings — changing these triggers the protection
define('_ATTR_CANONICAL', serialize([
    'author'  => 'Vineet',
    'email'   => 'psvineet@zohomail.in',
    'product' => 'DRXStore',
    'version' => '1.0',
]));

// Hash of the canonical attribution — stored at first boot
define('_ATTR_HASH', hash('sha256', _ATTR_CANONICAL));

function _attr_init(): void {
    $markerFile = DATA_DIR . '/.attr_marker';

    // First boot: write the marker
    if (!file_exists($markerFile)) {
        $payload = json_encode([
            'hash'      => _ATTR_HASH,
            'author'    => _AUTHOR_RAW,
            'email'     => _AUTHOR_EMAIL,
            'created'   => date('Y-m-d H:i:s'),
            'sig'       => base64_encode(_BUILD_SIG),
        ]);
        file_put_contents($markerFile, $payload);
        return;
    }

    // Subsequent boots: verify marker
    $stored = json_decode(file_get_contents($markerFile), true);
    if (!is_array($stored)) {
        _attr_restore($markerFile);
        return;
    }

    $storedHash = $stored['hash'] ?? '';
    if (!hash_equals(_ATTR_HASH, $storedHash)) {
        // Hash mismatch — marker was tampered
        _attr_restore($markerFile);
    }

    // Verify the decoded sig still matches
    $decodedSig = base64_decode($stored['sig'] ?? '');
    if ($decodedSig !== _BUILD_SIG) {
        _attr_restore($markerFile);
    }
}

function _attr_restore(string $markerFile): void {
    // Restore correct marker silently
    $payload = json_encode([
        'hash'    => _ATTR_HASH,
        'author'  => _AUTHOR_RAW,
        'email'   => _AUTHOR_EMAIL,
        'created' => date('Y-m-d H:i:s'),
        'sig'     => base64_encode(_BUILD_SIG),
    ]);
    file_put_contents($markerFile, $payload);
}

function attrFooter(): string {
    // Produces the "Developed by Vineet" line for footers/layouts
    // Encoded so naive string-replace won't find it
    $parts = ['D','e','v','e','l','o','p','e','d',' ','b','y',' '];
    $name  = ["\x56","\x69","\x6e","\x65","\x65","\x74"];
    $label = implode('', $parts) . implode('', $name);
    $email = _AUTHOR_EMAIL;
    return '<div class="sb-foot-attr">' . htmlspecialchars($label, ENT_QUOTES) . ' &nbsp;|&nbsp; <a href="mailto:' . $email . '">' . $email . '</a></div>';
}

function attrMeta(): string {
    // Invisible attribution embedded in HTML <head> — survives UI changes
    return '<meta name="generator" content="' . htmlspecialchars(_BUILD_SIG, ENT_QUOTES) . '">'
         . '<meta name="author" content="' . htmlspecialchars(_AUTHOR_RAW, ENT_QUOTES) . '">';
}

function attrComment(): string {
    // HTML comment with encoded attribution
    $line1 = base64_encode('Developed with 🫀 by ' . _AUTHOR_RAW);
    $line2 = base64_encode(_AUTHOR_EMAIL);
    $line3 = base64_encode(_BUILD_SIG);
    return "\n<!-- {$line1} -->\n<!-- {$line2} -->\n<!-- {$line3} -->\n";
}

// Run init check on every include
_attr_init();
