import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function FlashToasts() {
    const { flash } = usePage().props;

    useEffect(() => {
        // Visual toast via simple fixed banner; auto-clears with next navigation
    }, [flash]);

    if (!flash?.success && !flash?.error && !flash?.warning) {
        return null;
    }

    const message = flash.success || flash.error || flash.warning;
    const tone = flash.success
        ? 'bg-green-700 text-white'
        : flash.error
          ? 'bg-error text-white'
          : 'bg-amber-600 text-white';

    return (
        <div className={`fixed right-4 top-4 z-[100] max-w-sm rounded-xl px-4 py-3 shadow-elevated ${tone}`}>
            {message}
        </div>
    );
}
