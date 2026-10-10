import { useState } from 'react';

export default function ProductImage({ src, alt, className = '' }) {
    const [failedSrc, setFailedSrc] = useState(null);
    return src && failedSrc !== src
        ? <img src={src} alt={alt} className={className} onError={() => setFailedSrc(src)} />
        : <div role="img" aria-label={alt} className={`flex items-center justify-center bg-slate-100 text-sm text-slate-500 ${className}`}>Brak zdjęcia</div>;
}
