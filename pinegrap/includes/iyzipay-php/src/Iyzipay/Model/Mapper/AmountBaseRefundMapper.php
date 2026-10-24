<?php

namespace Iyzipay\Model\Mapper;

use Iyzipay\Model\AmountBaseRefund;

class AmountBaseRefundMapper extends IyzipayResourceMapper {
    public static function create($rawResult = null): AmountBaseRefundMapper {
        return new AmountBaseRefundMapper($rawResult);
    }

    // \stdClass rather than object: PHP 7.1 reads an "object" parameter type
    // as a class named Iyzipay\Model\Mapper\object, which no decoded answer
    // is. json_decode() gives a \stdClass for every JSON object; any other
    // body (an HTML error page, nothing) is still a TypeError here, which
    // includes/payments/iyzico.php reads as an answer it cannot read.
    public function mapAmountBaseRefundFrom(AmountBaseRefund $amountBaseRefund, \stdClass $jsonObject): AmountBaseRefund {
        parent::mapResourceFrom($amountBaseRefund, $jsonObject);

        if (isset($jsonObject->paymentId)) {
            $amountBaseRefund->setPaymentId($jsonObject->paymentId);
        }

        if (isset($jsonObject->price)) {
            $amountBaseRefund->setPrice($jsonObject->price);
        }

        if (isset($jsonObject->ip)) {
            $amountBaseRefund->setIp($jsonObject->ip);
        }

        return $amountBaseRefund;
    }

    public function mapAmountBaseRefund(AmountBaseRefund $amountBaseRefund): AmountBaseRefund {
        return $this->mapAmountBaseRefundFrom($amountBaseRefund, $this->jsonObject);
    }
}
