<?php

namespace Mollie\Adapter;

use Language as PrestashopLanguage;

class Language
{
    public function getAllLanguages(): array
    {
        return PrestashopLanguage::getLanguages(false);
    }
}
