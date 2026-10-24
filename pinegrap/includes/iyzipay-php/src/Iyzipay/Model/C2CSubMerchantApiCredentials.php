<?php

namespace Iyzipay\Model;

class C2CSubMerchantApiCredentials {
    private $salt;
    private $secretKey;

    public function getSalt(): ?string {
        return $this->salt ?? null;
    }

    public function setSalt(string $salt): void {
        $this->salt = $salt;
    }

    public function getSecretKey(): ?string {
        return $this->secretKey ?? null;
    }

    public function setSecretKey(string $secretKey): void {
        $this->secretKey = $secretKey;
    }
}
