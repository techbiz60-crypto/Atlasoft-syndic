<?php

namespace App;

enum SubscriptionPlan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Standard = 'standard';
    case Plus = 'plus';
    case Premium = 'premium';
    case Custom = 'custom';

    public static function forLotsCount(int $lotsCount): self
    {
        return match (true) {
            $lotsCount <= 6 => self::Free,
            $lotsCount <= 15 => self::Starter,
            $lotsCount <= 40 => self::Standard,
            $lotsCount <= 70 => self::Plus,
            $lotsCount <= 100 => self::Premium,
            default => self::Custom,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratuit',
            self::Starter => 'Starter',
            self::Standard => 'Standard',
            self::Plus => 'Plus',
            self::Premium => 'Premium',
            self::Custom => 'Sur devis',
        };
    }

    /**
     * Monthly price in DH, or null for plans without a fixed price (free, custom).
     */
    public function monthlyPrice(): ?int
    {
        return match ($this) {
            self::Free => 0,
            self::Starter => 50,
            self::Standard => 100,
            self::Plus => 160,
            self::Premium => 220,
            self::Custom => null,
        };
    }

    /**
     * Annual price in DH (~20% discount vs. 12x the monthly price), or null
     * for plans without a fixed price (free, custom).
     */
    public function annualPrice(): ?int
    {
        $monthly = $this->monthlyPrice();

        return $monthly !== null ? (int) round($monthly * 12 * 0.8) : null;
    }

    /**
     * Maximum number of lots (apartments) this plan allows, or null for
     * plans with no fixed ceiling (sur devis / custom pricing).
     */
    public function maxLots(): ?int
    {
        return match ($this) {
            self::Free => 6,
            self::Starter => 15,
            self::Standard => 40,
            self::Plus => 70,
            self::Premium => 100,
            self::Custom => null,
        };
    }
}
