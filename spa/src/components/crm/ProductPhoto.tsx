import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { LuChevronLeft, LuChevronRight, LuEye } from '@/lib/icons';

export interface ProductPhotoDetails {
  url: string;
  name: string;
  partNumber?: string;
  altText?: string;
}

export interface ProductPhotoGallery {
  photos: ProductPhotoDetails[];
  initialIndex?: number;
}

export function ProductPhotoButton({
  photo,
  photoCount,
  className,
  onView,
}: {
  photo: ProductPhotoDetails;
  photoCount?: number;
  className: string;
  onView: (photo: ProductPhotoDetails) => void;
}) {
  const label = photoCount && photoCount > 1
    ? `View ${photoCount} photos of ${photo.name}`
    : `View larger photo of ${photo.altText || photo.name}`;

  return (
    <button
      type="button"
      aria-label={label}
      title={photoCount && photoCount > 1 ? `View ${photoCount} product photos` : 'View larger photo'}
      className={`group relative block shrink-0 cursor-zoom-in overflow-hidden rounded border border-default bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ${className}`}
      onClick={(event) => {
        event.stopPropagation();
        onView(photo);
      }}
    >
      <img src={photo.url} alt={photo.altText || ''} loading="lazy" decoding="async" className="h-full w-full object-cover" />
      <span aria-hidden="true" className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded border border-default bg-canvas text-primary opacity-100 transition-opacity md:opacity-0 md:group-hover:opacity-100 md:group-focus-visible:opacity-100">
        <LuEye size={13} />
      </span>
      {photoCount != null && photoCount > 1 && (
        <span aria-hidden="true" className="absolute bottom-0.5 right-0.5 min-w-4 rounded border border-default bg-canvas px-1 text-center font-mono text-[10px] leading-4 text-primary">
          {photoCount}
        </span>
      )}
    </button>
  );
}

export function ProductPhotoGalleryViewer({
  gallery,
  onClose,
}: {
  gallery: ProductPhotoGallery | null;
  onClose: () => void;
}) {
  const [activeIndex, setActiveIndex] = useState(gallery?.initialIndex ?? 0);
  const photos = gallery?.photos ?? [];

  useEffect(() => {
    if (!gallery) return;
    const lastIndex = Math.max(0, gallery.photos.length - 1);
    setActiveIndex(Math.min(Math.max(gallery.initialIndex ?? 0, 0), lastIndex));
  }, [gallery]);

  const activePhoto = photos[activeIndex] ?? photos[0];

  return (
    <Modal isOpen={activePhoto !== undefined} onClose={onClose} title={activePhoto?.name ?? 'Product photos'} size="xl">
      {activePhoto && (
        <div className="space-y-3">
          <figure className="m-0 space-y-3">
            <div className="flex min-h-48 items-center justify-center rounded border border-default bg-surface p-3 sm:min-h-64">
              <img
                src={activePhoto.url}
                alt={activePhoto.altText || `${activePhoto.name} product photo`}
                className="mx-auto max-h-[58dvh] w-auto max-w-full object-contain"
              />
            </div>
            {activePhoto.partNumber && (
              <figcaption className="text-center font-mono text-xs text-muted">
                {activePhoto.partNumber}
              </figcaption>
            )}
          </figure>

          {photos.length > 1 && (
            <>
              <div className="flex items-center justify-center gap-3">
                <Button type="button" variant="ghost" size="touch" iconOnly
                  aria-label={`Previous photo of ${activePhoto.name}`} disabled={activeIndex === 0}
                  icon={<LuChevronLeft size={18} />} onClick={() => setActiveIndex((index) => Math.max(0, index - 1))} />
                <span role="status" className="min-w-24 text-center font-mono text-xs tabular-nums text-muted">
                  Photo {activeIndex + 1} of {photos.length}
                </span>
                <Button type="button" variant="ghost" size="touch" iconOnly
                  aria-label={`Next photo of ${activePhoto.name}`} disabled={activeIndex >= photos.length - 1}
                  icon={<LuChevronRight size={18} />} onClick={() => setActiveIndex((index) => Math.min(photos.length - 1, index + 1))} />
              </div>
              <div role="group" aria-label={`Photos of ${activePhoto.name}`} className="flex max-w-full gap-2 overflow-x-auto pb-1">
                {photos.map((photo, index) => (
                  <button key={`${photo.url}-${index}`} type="button"
                    aria-label={`Show photo ${index + 1} of ${photos.length}${photo.altText ? `: ${photo.altText}` : ''}`}
                    aria-pressed={index === activeIndex}
                    className={`h-14 w-14 shrink-0 overflow-hidden rounded border bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ${index === activeIndex ? 'border-strong ring-1 ring-accent' : 'border-default'}`}
                    onClick={() => setActiveIndex(index)}>
                    <img src={photo.url} alt="" loading="lazy" decoding="async" className="h-full w-full object-cover" />
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      )}
    </Modal>
  );
}

export function ProductPhotoViewer({
  photo,
  onClose,
}: {
  photo: ProductPhotoDetails | null;
  onClose: () => void;
}) {
  return (
    <Modal isOpen={photo !== null} onClose={onClose} title={photo?.name ?? 'Product photo'} size="xl">
      {photo && (
        <figure className="space-y-3">
          <img
            src={photo.url}
            alt={photo.altText || `${photo.name} product photo`}
            className="mx-auto max-h-[72vh] w-auto max-w-full rounded object-contain"
          />
          {photo.partNumber && (
            <figcaption className="text-center font-mono text-xs text-muted">
              {photo.partNumber}
            </figcaption>
          )}
        </figure>
      )}
    </Modal>
  );
}
