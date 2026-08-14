<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Support;

use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceCustomers\Data\Money;
use Liberu\Ecommerce\CommerceCustomers\Data\PurchaseHistoryResult;
use Liberu\Ecommerce\CommerceCustomers\Enums\ConsentChannel;
use Liberu\Ecommerce\CommerceCustomers\Models\CustomerFile;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetCurrentConsent;
use Liberu\Ecommerce\CommerceCustomers\Queries\GetPurchaseHistory;

/**
 * How this panel renders the answers the domain publishes.
 *
 * It computes nothing. Every method here takes a published result and turns it
 * into a string a merchant reads, which is the only thing a presentation
 * package is allowed to add.
 */
final class CustomerFilePresenter
{
    /** A person's file with no reference to a person is a guest file, and has no history to ask about. */
    public static function purchaseHistory(CustomerFile $file): PurchaseHistoryResult
    {
        if ($file->person_ref === null) {
            return PurchaseHistoryResult::unavailable(
                'This is a guest file with no person reference, so there is nobody to ask Orders about.'
            );
        }

        return App::make(GetPurchaseHistory::class)($file->tenant_id, $file->person_ref);
    }

    /**
     * A lifetime value is a map of currency to amount and is rendered as one.
     *
     * The host summed orders in every currency with `sum('total_amount')` and
     * called the result a customer's worth. Two currencies do not add; folding
     * them into one figure invents a number with no unit, and a merchant acts
     * on it. So this returns one line per currency and an explicit "nothing
     * recorded" when the map is empty — never a single total, and never a zero
     * standing in for an absent currency.
     *
     * @return list<string>
     */
    public static function lifetimeValueLines(PurchaseHistoryResult $result): array
    {
        if (! $result->available || $result->history === null) {
            return [self::unavailableLine($result)];
        }

        $value = $result->history->lifetimeValue;

        if ($value->isEmpty()) {
            return ['No purchases recorded in any currency.'];
        }

        return array_values(array_map(
            static fn (Money $money): string => $money->currency.' '.$money->decimal(),
            $value->amounts,
        ));
    }

    /**
     * The one sentence a merchant sees when Orders is not composed in.
     *
     * Deliberately not "0 orders, 0.00 spent". Zero and no answer are the same
     * number and opposite facts, and the merchant who reads a zero will act on
     * it — refuse a refund, close a file, drop someone from a campaign.
     */
    public static function unavailableLine(PurchaseHistoryResult $result): string
    {
        return 'Not available — '.($result->reason ?? 'no purchase-history resolver is bound.');
    }

    public static function orderCountLine(PurchaseHistoryResult $result): string
    {
        if (! $result->available || $result->history === null) {
            return self::unavailableLine($result);
        }

        return (string) $result->history->orderCount;
    }

    public static function purchaseInstantLine(PurchaseHistoryResult $result, bool $first): string
    {
        if (! $result->available || $result->history === null) {
            return self::unavailableLine($result);
        }

        $instant = $first ? $result->history->firstPurchaseAt : $result->history->lastPurchaseAt;

        return $instant?->toDayDateTimeString() ?? 'Never';
    }

    /**
     * Consent as the domain folds it: every channel present, and the ones
     * nobody has decided reading Unknown rather than Withdrawn. Never asked and
     * said no are different facts.
     *
     * @return list<string>
     */
    public static function consentLines(CustomerFile $file): array
    {
        $lines = [];

        foreach (App::make(GetCurrentConsent::class)($file) as $channel => $state) {
            $lines[] = ConsentChannel::from($channel)->label().': '.$state->label();
        }

        return $lines;
    }

    /** The display name a merchant reads, which may legitimately be nothing at all. */
    public static function displayName(CustomerFile $file): string
    {
        $name = trim(($file->given_name ?? '').' '.($file->family_name ?? ''));

        return $name === '' ? '—' : $name;
    }
}
