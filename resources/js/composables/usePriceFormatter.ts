// composables/usePriceFormatter.ts
export const usePriceFormatter = () => {
    // Convert to number safely
    const toNumber = (value: number | string | null | undefined): number => {
        if (value === null || value === undefined){
            return 0;
        }

        if (typeof value === 'string') {
            const parsed = parseFloat(value);

            return isNaN(parsed) ? 0 : parsed;
        }

        return isNaN(value) ? 0 : value;
    };

    // composables/usePriceFormatter.ts
    const formatPrice = (price: number | string | null | undefined): string => {
        const num = toNumber(price);
        
        // Check if it's a whole number
        if (num % 1 === 0) {
            return num.toLocaleString(); // "1,500"
        }
        
        // Show 2 decimals only if needed
        return num.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }); // "1,500.50"
    };

    const formatPriceWithCurrency = (price: number | string | null | undefined, currency: string = 'KES'): string => {
        const num = toNumber(price);

        return `${currency} ${num.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        })}`;
    };

    const calculateDiscountPct = (originalPrice: number | string | null | undefined, discountedPrice: number | string | null | undefined): number | null => {
        const original = toNumber(originalPrice);
        const discounted = toNumber(discountedPrice);
        
        if (!original || original <= 0 || discounted >= original) {
            return null;
        }

        return Math.round(((original - discounted) / original) * 100);
    };

    const getDiscountedPrice = (price: number | string | null | undefined, discountPct: number): number => {
        const original = toNumber(price);

        return Number((original - (original * (discountPct / 100))).toFixed(2));
    };

    return {
        formatPrice,
        formatPriceWithCurrency,
        calculateDiscountPct,
        getDiscountedPrice,
        toNumber
    };
};