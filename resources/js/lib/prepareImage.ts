/**
 * Shrinks a photo in the browser before it is uploaded.
 *
 * Phone cameras produce 3-10 MB photos, far more than the shop needs and slow
 * on mobile data. This scales the photo down so its longest side is at most
 * `maxSide` pixels and saves it as a JPEG (the server then makes the final
 * padded, square WebP versions). The photo is rotated upright first, so phone
 * photos taken sideways are not uploaded sideways.
 *
 * If anything goes wrong (very old browser, photo too big to decode, ...) the
 * original file is returned and the server deals with it.
 */
export interface PrepareImageOptions {
    /** Longest side in pixels after shrinking. */
    maxSide?: number;
    /** JPEG quality from 0 to 1. */
    quality?: number;
    /** A JPEG or WebP that is already within maxSide and under this many bytes is left alone. */
    keepIfSmallerThan?: number;
}

export async function prepareImageForUpload(
    file: File,
    options: PrepareImageOptions = {},
): Promise<File> {
    const { maxSide = 2000, quality = 0.85, keepIfSmallerThan = 1.5 * 1024 * 1024 } = options;

    if (!file.type.startsWith('image/')) {
        return file;
    }

    let source: ImageBitmap | HTMLImageElement | null = null;
    let objectUrl: string | null = null;

    try {
        source = await decode(file).catch(async () => {
            objectUrl = URL.createObjectURL(file);

            return await loadImageElement(objectUrl);
        });

        const width = 'naturalWidth' in source ? source.naturalWidth : source.width;
        const height = 'naturalHeight' in source ? source.naturalHeight : source.height;

        if (!width || !height) {
            return file;
        }

        const scale = Math.min(1, maxSide / Math.max(width, height));
        const isWebFriendly = file.type === 'image/jpeg' || file.type === 'image/webp';

        if (scale === 1 && isWebFriendly && file.size <= keepIfSmallerThan) {
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));

        const context = canvas.getContext('2d');

        if (!context) {
            return file;
        }

        // JPEG has no transparency: put transparent PNGs on white
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise<Blob | null>((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', quality);
        });

        // Never make a photo bigger than it was
        if (!blob || (scale === 1 && blob.size >= file.size)) {
            return file;
        }

        return new File([blob], `${baseName(file.name)}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch {
        return file;
    } finally {
        if ('close' in (source ?? {})) {
            (source as ImageBitmap).close();
        }

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }
    }
}

/** Decode with the camera's rotation (EXIF) already applied. */
function decode(file: File): Promise<ImageBitmap> {
    if (typeof createImageBitmap !== 'function') {
        return Promise.reject(new Error('createImageBitmap is not supported'));
    }

    return createImageBitmap(file, { imageOrientation: 'from-image' });
}

function loadImageElement(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('The image could not be read'));
        image.src = url;
    });
}

function baseName(filename: string): string {
    return filename.replace(/\.[^.]+$/, '') || 'photo';
}