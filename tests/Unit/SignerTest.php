<?php

use Jeidison\PdfSigner\Metadata;
use Jeidison\PdfSigner\Signer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SignerTest extends TestCase
{
    #[DataProvider('providerSign')]
    public function testSign(string $password)
    {
        $fileContent = file_get_contents(__DIR__.'/../fixtures/small_valid.pdf');
        $certificate = $this->getNewCert($password);
        $signedContent = Signer::new()
            ->withCertificate($certificate, $password)
            ->withContent($fileContent)
            ->withMetadata(
                Metadata::new()
                    ->withReason('ASSINATURA DE DOCUMENTOS PARA TESTES.')
                    ->withName('JEIDISON SANTOS FARIAS')
                    ->withLocation('Araras/SP')
                    ->withContactInfo('Jeidison Farias <jeidison.farias@gmail.com>')
            )
            ->sign();

        file_put_contents(__DIR__.'/../temp/'.$password.'.pdf', $signedContent);
        $this->assertGreaterThan(10, strlen($signedContent));
    }

    public static function providerSign(): array
    {
        return [
            ['9812'],
            ['jeidison1809'],
            ['msvppsvtsd1234'],
            ['1234'],
        ];
    }
}
