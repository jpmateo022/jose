<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2016 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Jose\Object;

use Assert\Assertion;
use Base64Url\Base64Url;

/**
 * Class JWS.
 */
final class JWS implements JWSInterface
{
    use JWT;

    /**
     * @var \Jose\Object\SignatureInterface[]
     */
    private $signatures = [];

    /**
     * {@inheritdoc}
     */
    public function getEncodedPayload()
    {
        $payload = $this->getPayload();
        if (null === $payload) {
            return;
        } elseif (is_string($payload)) {
            return Base64Url::encode($payload);
        }
        $encoded = json_encode($payload);
        Assertion::notNull($encoded, 'Unsupported payload.');

        return Base64Url::encode($encoded);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatures()
    {
        return $this->signatures;
    }

    /**
     * {@inheritdoc}
     */
    public function &getSignature($id)
    {
        if (isset($this->signatures[$id])) {
            return $this->signatures[$id];
        }
        throw new \InvalidArgumentException('The signature does not exist.');
    }

    /**
     * {@inheritdoc}
     */
    public function addSignature(JWKInterface $signature_key, array $protected_headers, array $headers = [])
    {
        $jws = clone $this;
        $jws->signatures[] = Signature::createSignature($signature_key, $protected_headers, $headers);

        return $jws;
    }

    /**
     * {@inheritdoc}
     */
    public function addSignatureFromLoadedData($signature, $encoded_protected_headers, array $headers)
    {
        $jws = clone $this;
        $jws->signatures[] = Signature::createSignatureFromLoadedData($signature, $encoded_protected_headers, $headers);

        return $jws;
    }

    /**
     * {@inheritdoc}
     */
    public function countSignatures()
    {
        return count($this->signatures);
    }

    /**
     * {@inheritdoc}
     */
    public function toCompactJSON($id)
    {
        $signature = $this->getSignature($id);

        Assertion::true(
            empty($signature->getHeaders()),
            'The signature contains unprotected headers and cannot be converted into compact JSON'
        );

        return sprintf(
            '%s.%s.%s',
            $signature->getEncodedProtectedHeaders(),
            $this->getEncodedPayload(),
            Base64Url::encode($signature->getSignature())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toFlattenedJSON($id)
    {
        $signature = $this->getSignature($id);

        $data = [];
        $values = [
            'payload'   => $this->getEncodedPayload(),
            'protected' => $signature->getEncodedProtectedHeaders(),
            'header'    => $signature->getHeaders(),
        ];

        foreach ($values as $key => $value) {
            if (!empty($value)) {
                $data[$key] = $value;
            }
        }
        $data['signature'] = Base64Url::encode($signature->getSignature());

        return json_encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function toJSON()
    {
        Assertion::greaterThan($this->countSignatures(), 0, 'No signature.');

        $data = [];
        if (!empty($this->getEncodedPayload())) {
            $data['payload'] = $this->getEncodedPayload();
        }

        $data['signatures'] = [];
        foreach ($this->getSignatures() as $signature) {
            $tmp = ['signature' => Base64Url::encode($signature->getSignature())];
            $values = [
                'protected' => $signature->getEncodedProtectedHeaders(),
                'header'    => $signature->getHeaders(),
            ];

            foreach ($values as $key => $value) {
                if (!empty($value)) {
                    $tmp[$key] = $value;
                }
            }
            $data['signatures'][] = $tmp;
        }

        return json_encode($data);
    }
}
