<?php

declare(strict_types=1);

namespace SignerPHP\Application\DTO;

final class BrazilPolicyLpaUrlsDto
{
    public function __construct(
        public readonly string $lpaUrlAsn1Pades = 'https://politicas.icpbrasil.gov.br/LPA_PAdES.der',
        public readonly string $lpaUrlAsn1SignaturePades = 'https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s',
    ) {}
}
