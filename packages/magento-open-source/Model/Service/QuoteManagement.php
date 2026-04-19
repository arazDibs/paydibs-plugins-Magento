<?php
/**
 * Copyright 2026 Paydibs
 * SPDX-License-Identifier: Apache-2.0
 */
namespace Paydibs\PaymentGateway\Model\Service;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Paydibs\PaymentGateway\Model\PaymentMethod;

/**
 * Quote lifecycle helpers for Paydibs redirect flow (no ObjectManager).
 */
class QuoteManagement
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var PaymentMethod
     */
    private $paymentMethod;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession,
        PaymentMethod $paymentMethod
    ) {
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Deactivate quote after successful payment (clear cart).
     *
     * @param int|string|null $quoteId
     */
    public function deactivateQuote($quoteId): bool
    {
        if (!$quoteId) {
            return false;
        }
        try {
            $quote = $this->cartRepository->get((int) $quoteId);
            $quote->setIsActive(false);
            $this->cartRepository->save($quote);
            $this->paymentMethod->log('Quote: Cart deactivated for quote ID: ' . $quoteId);
            return true;
        } catch (NoSuchEntityException $e) {
            $this->paymentMethod->log('Quote: Failed to clear cart — quote not found for ID: ' . $quoteId);
            return false;
        } catch (\Throwable $e) {
            $this->paymentMethod->log('Quote: Error clearing cart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reactivate quote and bind it to checkout session (failed / cancelled payment).
     *
     * @param int|string|null $quoteId
     */
    public function restoreQuoteForCheckout($quoteId): bool
    {
        if (!$quoteId) {
            return false;
        }
        try {
            $quote = $this->cartRepository->get((int) $quoteId);
            $quote->setIsActive(true);
            $quote->setReservedOrderId(null);
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);
            $this->paymentMethod->log('Quote: Quote restored for ID: ' . $quoteId);
            return true;
        } catch (NoSuchEntityException $e) {
            $this->paymentMethod->log('Quote: Failed to restore quote — not found for ID: ' . $quoteId);
            return false;
        } catch (\Throwable $e) {
            $this->paymentMethod->log('Quote: Error restoring quote: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Keep quote active in session when order is placed but payment is external (cart restoration mode).
     *
     * @param int|string|null $quoteId
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function reactivateQuoteInSession($quoteId): void
    {
        $quote = $this->cartRepository->get((int) $quoteId);
        $quote->setIsActive(true);
        $this->cartRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote);
    }
}
