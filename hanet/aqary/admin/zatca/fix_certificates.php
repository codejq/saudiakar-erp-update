<?php
/**
 * ZATCA Certificate Directory Fix
 *
 * Fixes certificate directory permissions and structure
 *
 * @charset UTF-8
 */

echo "=== ZATCA Certificate Directory Fix ===\n\n";

$certDir = __DIR__ . '/certificates';

// Step 1: Create certificates directory
echo "Step 1: Creating certificates directory...\n";
if (!file_exists($certDir)) {
    if (mkdir($certDir, 0700, true)) {
        echo "✅ Directory created: $certDir\n";
    } else {
        echo "❌ Failed to create directory: $certDir\n";
        echo "Error: " . error_get_last()['message'] . "\n";
        exit(1);
    }
} else {
    echo "✅ Directory already exists: $certDir\n";
}

// Step 2: Set proper permissions
echo "\nStep 2: Setting directory permissions...\n";
if (chmod($certDir, 0700)) {
    echo "✅ Permissions set to 0700 (owner read/write/execute only)\n";
} else {
    echo "⚠️ Warning: Could not set permissions (may need manual fix)\n";
}

// Step 3: Check if directory is writable
echo "\nStep 3: Testing write permissions...\n";
$testFile = $certDir . '/test.txt';
if (file_put_contents($testFile, 'test')) {
    echo "✅ Directory is writable\n";
    unlink($testFile);
} else {
    echo "❌ Directory is NOT writable\n";
    echo "Please run: chmod 700 " . $certDir . "\n";
    exit(1);
}

// Step 4: Create .gitignore
echo "\nStep 4: Creating .gitignore...\n";
$gitignore = $certDir . '/.gitignore';
$gitignoreContent = "# Ignore all certificate files for security
*.key
*.pem
*.csr
*.p12
*.pfx
secret.txt
production_*.key
production_*.pem

# But keep the directory structure
!.gitignore
!README.md
";

if (file_put_contents($gitignore, $gitignoreContent)) {
    echo "✅ .gitignore created\n";
} else {
    echo "⚠️ Warning: Could not create .gitignore\n";
}

// Step 5: Create README
echo "\nStep 5: Creating README...\n";
$readme = $certDir . '/README.md';
$readmeContent = "# ZATCA Certificates Directory

This directory stores ZATCA Phase 2 certificates and private keys.

## Security Notice
⚠️ **NEVER commit certificate files to version control!**

## Files
- `private.key` - Private key (generated)
- `certificate.csr` - Certificate Signing Request (generated)
- `certificate.pem` - Certificate from ZATCA (manual)
- `secret.txt` - API secret from ZATCA (manual)

## Permissions
This directory should have permissions 700 (owner only).
Certificate files should have permissions 600 (owner read/write only).

## Generation
Use the Phase 2 setup wizard to generate certificates:
http://your-domain/admin/zatca/phase2_setup.php
";

if (file_put_contents($readme, $readmeContent)) {
    echo "✅ README.md created\n";
} else {
    echo "⚠️ Warning: Could not create README.md\n";
}

// Step 6: Check OpenSSL
echo "\nStep 6: Checking OpenSSL...\n";
if (extension_loaded('openssl')) {
    echo "✅ OpenSSL extension is loaded\n";
    echo "OpenSSL version: " . OPENSSL_VERSION_TEXT . "\n";
} else {
    echo "❌ OpenSSL extension is NOT loaded\n";
    echo "Please enable OpenSSL in php.ini\n";
    exit(1);
}

// Step 7: Test OpenSSL key generation
echo "\nStep 7: Testing OpenSSL key generation...\n";

// Find or create OpenSSL config
function findOpenSSLConfig() {
    $possiblePaths = [
        '../../../../aso/Apache24/php-8.5.0-Win32-vs17-x64/extras/ssl/openssl.cnf',
        '../../../../aso/Apache24/extras/ssl/openssl.cnf',
        '../../OpenSSL-Win64/bin/openssl/openssl.exe',
        '../../../../../OpenSSL-Win64/bin/openssl.exe',
        'C:/wamp/bin/apache/apache2.4.46/conf/openssl.cnf',
        'C:/wamp64/bin/apache/apache2.4.46/conf/openssl.cnf',
        'C:/php/extras/openssl/openssl.cnf',
        getenv('OPENSSL_CONF'),
        dirname(php_ini_loaded_file()) . '/extras/openssl/openssl.cnf',
    ];

    foreach ($possiblePaths as $path) {
        if ($path && file_exists($path)) {
            echo "✅ Found OpenSSL config at: $path\n";
            return $path;
        }
    }

    return null;
}

function createTempOpenSSLConfig() {
    $tempDir = sys_get_temp_dir();
    $configPath = $tempDir . '/zatca_openssl.cnf';

    $configContent = <<<EOT
HOME            = .
RANDFILE        = \$ENV::HOME/.rnd

[ req ]
default_bits        = 2048
default_keyfile     = privkey.pem
distinguished_name  = req_distinguished_name
x509_extensions     = v3_ca
string_mask         = utf8only

[ req_distinguished_name ]
countryName                     = Country Name (2 letter code)
countryName_default             = SA
countryName_min                 = 2
countryName_max                 = 2

[ v3_ca ]
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always,issuer
basicConstraints = CA:true

EOT;

    file_put_contents($configPath, $configContent);
    echo "✅ Created temporary OpenSSL config at: $configPath\n";
    return $configPath;
}

$opensslConfig = findOpenSSLConfig();
if (!$opensslConfig) {
    $opensslConfig = createTempOpenSSLConfig();
}

$testKeyFile = $certDir . '/test_key.pem';
$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config' => $opensslConfig,
];

$privateKey = openssl_pkey_new($config);
if ($privateKey) {
    echo "✅ OpenSSL can generate keys\n";

    // Try to export to file
    if (openssl_pkey_export_to_file($privateKey, $testKeyFile, null, $config)) {
        echo "✅ OpenSSL can write key files\n";
        unlink($testKeyFile);
    } else {
        echo "❌ OpenSSL cannot write key files\n";
        echo "Error: " . openssl_error_string() . "\n";
        exit(1);
    }
} else {
    echo "❌ OpenSSL cannot generate keys\n";
    echo "Error: " . openssl_error_string() . "\n";
    echo "\nTroubleshooting:\n";
    echo "- Check if temp directory is writable: " . sys_get_temp_dir() . "\n";
    echo "- OpenSSL config used: " . $opensslConfig . "\n";
    exit(1);
}

// Step 8: Summary
echo "\n=== Summary ===\n";
echo "✅ Certificate directory is ready\n";
echo "✅ Permissions are correct\n";
echo "✅ OpenSSL is working\n";
echo "\nDirectory: $certDir\n";
echo "Permissions: " . substr(sprintf('%o', fileperms($certDir)), -4) . "\n";
echo "\n=== Next Steps ===\n";
echo "1. Go to: http://your-domain/admin/zatca/phase2_setup.php\n";
echo "2. Click 'توليد CSR' to generate certificates\n";
echo "3. Submit CSR to ZATCA portal\n";
echo "\n✅ Fix completed successfully!\n";
?>
