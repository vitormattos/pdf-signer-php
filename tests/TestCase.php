<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getNewCert($password)
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csrNames = ['commonName' => 'Jhon Doe'];

        $csr = openssl_csr_new($csrNames, $privateKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365, ['digest_alg' => 'sha256']);

        openssl_x509_export($x509, $rootCertificate);
        openssl_pkey_export($privateKey, $rootPrivateKey);

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'cfg');
        $csr = openssl_csr_new($csrNames, $privateKey);
        $x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, 365);
        $certContent = null;
        openssl_pkcs12_export(
            $x509,
            $certContent,
            $privateKey,
            $password,
        );
        return $certContent;
    }
}
