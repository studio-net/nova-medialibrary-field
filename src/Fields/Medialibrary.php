<?php declare(strict_types=1);

namespace DmitryBubyakin\NovaMedialibraryField\Fields;

use function DmitryBubyakin\NovaMedialibraryField\callable_or_default;
use DmitryBubyakin\NovaMedialibraryField\Fields\Support\AttachCallback;
use DmitryBubyakin\NovaMedialibraryField\Fields\Support\MediaCollectionRules;
use DmitryBubyakin\NovaMedialibraryField\Fields\Support\MediaFields;
use DmitryBubyakin\NovaMedialibraryField\Fields\Support\MediaPresenter;
use DmitryBubyakin\NovaMedialibraryField\Fields\Support\ResolveMediaCallback;
use DmitryBubyakin\NovaMedialibraryField\TransientModel;
use function DmitryBubyakin\NovaMedialibraryField\validate_args;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Medialibrary extends Field
{
    public $component = 'nova-medialibrary-field';

    public $collectionName;

    public $diskName;

    public $fieldsCallback;

    public $attachCallback;

    public $copyAs = [];

    public $attachExistingCallback;

    public $resolveMediaUsingCallback;

    public $mediaOnIndexCallback;

    public $downloadCallback;

    public $previewCallback;

    public $appendTimestampToPreview = false;

    public $tooltipCallback;

    public $titleCallback;

    public $single;

    public $cropperConversion;

    public $cropperOptionsCallback;

    public $moveMediaToTargetModelCallback;

    public $attachRules;

    public $preventUpload;

    public function __construct(string $name, string $collectionName = '', string $diskName = '', string $attribute = null)
    {
        parent::__construct($name, $attribute);

        $this->collectionName = $collectionName;
        $this->diskName = $diskName;
        $this->fields(MediaFields::make());
        $this->attachUsing(new AttachCallback);
        $this->resolveMediaUsing(new ResolveMediaCallback);
        $this->single(false);
        $this->mediaOnIndex(1);
        $this->preventUpload(false);
        $this->attachRules([]);
        $this->resolve(null);
    }

    public function attribute(string $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function fields(callable $callback): self
    {
        $this->fieldsCallback = $callback;

        return $this;
    }

    public function attachUsing(callable $callback): self
    {
        $this->attachCallback = $callback;

        return $this;
    }

    /**
     * @param string|callable|null $callback
     */
    public function attachExisting($callback = null): self
    {
        validate_args();

        $this->attachExistingCallback = callable_or_default(
            $callback,
            function (Builder $query) use ($callback): void {
                if ($callback) {
                    $query->where('collection_name', $callback);
                }
            }
        );

        return $this->withMeta(['attachExisting' => true]);
    }

    public function resolveMediaUsing(callable $mediaCallback): self
    {
        $this->resolveMediaUsingCallback = $mediaCallback;

        return $this;
    }

    /**
     * @param int|callable $mediaOnIndex
     */
    public function mediaOnIndex($mediaOnIndex): self
    {
        validate_args();

        $this->mediaOnIndexCallback = callable_or_default(
            $mediaOnIndex,
            function (HasMedia $resource, string $collectionName) use ($mediaOnIndex): Collection {
                return $resource->media()->where('collection_name', $collectionName)
                                ->limit($mediaOnIndex)
                                ->orderBy('order_column')
                                ->get();
            }
        );

        return $this;
    }

    /**
     * @param string|callable $downloadUsing
     */
    public function downloadUsing($downloadUsing): self
    {
        validate_args();

        $this->downloadCallback = callable_or_default(
            $downloadUsing,
            function (Media $media) use ($downloadUsing): ?string {
                return $media->getFullUrl($downloadUsing);
            }
        );

        return $this;
    }

    /**
     * @param string|callable $previewUsing
     */
    public function previewUsing($previewUsing): self
    {
        validate_args();

        $this->previewCallback = callable_or_default(
            $previewUsing,
            function (Media $media) use ($previewUsing): ?string {
                return $media->getFullUrl($previewUsing);
            }
        );

        return $this;
    }

    public function appendTimestampToPreview(bool $append = true): self
    {
        $this->appendTimestampToPreview = $append;

        return $this;
    }

    /**
     * @param string|callable $tooltip
     */
    public function tooltip($tooltip): self
    {
        validate_args();

        $this->tooltipCallback = callable_or_default(
            $tooltip,
            function (Media $media) use ($tooltip): ?string {
                return $media->{$tooltip};
            }
        );

        return $this;
    }

    /**
     * @param string|callable $title
     */
    public function title($title): self
    {
        validate_args();

        $this->titleCallback = callable_or_default(
            $title,
            function (Media $media) use ($title): ?string {
                return $media->{$title};
            }
        );

        return $this;
    }

    /**
     * @param string|callable $value
     */
    public function copyAs(string $as, $value, string $icon = 'link'): self
    {
        validate_args();

        $this->copyAs[] = [
            $as,
            $icon,
            callable_or_default(
                $value,
                function (Media $media) use ($value): ?string {
                    return $media->{$value};
                }
            ),
        ];

        return $this;
    }

    public function hideCopyUrlAction(): self
    {
        return $this->withMeta([
            'hideCopyUrlAction' => true,
        ]);
    }

    /**
     * @param string $conversion
     * @param array|callable|null $options
     */
    public function croppable($conversion, $options = null): self
    {
        validate_args();

        $this->cropperConversion = $conversion;

        $this->cropperOptionsCallback = callable_or_default(
            $options,
            function () use ($options) {
                return $options ?: [
                    'viewMode' => 3,
                ];
            }
        );

        $this->appendTimestampToPreview(true);

        return $this;
    }

    public function moveMediaToTargetModelUsing(callable $moveMediaToTargetModelCallback): self
    {
        $this->moveMediaToTargetModelCallback = $moveMediaToTargetModelCallback;

        return $this;
    }

    public function single(bool $single = true): self
    {
        $this->single = $single;

        return $this;
    }

    public function accept(string $accept): self
    {
        return $this->withMeta(['accept' => $accept]);
    }

    public function maxSizeInBytes(int $maxSize): self
    {
        return $this->withMeta(['maxSize' => $maxSize]);
    }

    public function attachOnDetails(): self
    {
        return $this->withMeta(['attachOnDetails' => true]);
    }

    public function autouploading(): self
    {
        return $this->withMeta(['autouploading' => true]);
    }

    public function preventUpload($callable = true): self
    {
        $this->preventUpload = callable_or_default($callable, fn () => true)();

        return $this;
    }

    /**
     * @param \Illuminate\Contracts\Validation\Rule|string|array $rules
     */
    public function attachRules($rules): self
    {
        $this->attachRules = ($rules instanceof Rule || is_string($rules)) ? func_get_args() : $rules;

        return $this;
    }

    public function getAttachRules(NovaRequest $request): array
    {
        return is_callable($this->attachRules)
            ? call_user_func($this->attachRules, $request)
            : $this->attachRules;
    }

    public function getCreationRules(NovaRequest $request): array
    {
        return $this->makeMediaCollectionRules($request, parent::getCreationRules($request));
    }

    public function getUpdateRules(NovaRequest $request): array
    {
        return $this->makeMediaCollectionRules($request, parent::getUpdateRules($request));
    }

    protected function makeMediaCollectionRules(NovaRequest $request, array $rules): array
    {
        return [
            $this->attribute => MediaCollectionRules::make(
                $rules[$this->attribute],
                $request,
                $this,
            ),
        ];
    }

    public function resolve($resource, $attribute = null): void
    {
        $this->value = (string) Str::uuid();
    }

    public function resolveForDisplay($resource, $attribute = null): void
    {
        $indexControllerAction = 'Laravel\Nova\Http\Controllers\ResourceIndexController@handle';

        if (Route::current()->getActionName() === $indexControllerAction) {
            $this->resolveForIndex($resource, $attribute);
        }
    }

    public function resolveForIndex($resource, $attribute = null): void
    {
        $this->value = call_user_func_array($this->mediaOnIndexCallback, [
            $resource,
            $this->collectionName,
        ])->map(function (Media $media) {
            return new MediaPresenter($media, $this);
        });
    }

    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute): callable
    {
        $callback = null;

        if (is_callable($this->fillCallback)) {
            $callback = call_user_func(
                $this->fillCallback,
                $request,
                $model,
                $attribute,
                $requestAttribute
            );
        }

        if (is_a($model, \Whitecube\NovaFlexibleContent\Layouts\Layout::class)) {
            $model = \Whitecube\NovaFlexibleContent\Flexible::getOriginModel();
        }

        return function () use ($request, $attribute, $model, $callback): void {
            if (is_callable($callback)) {
                $callback();
            }

            if (empty($uuid = $request->input($attribute))) {
                return;
            }

            foreach (TransientModel::make()->getMedia($uuid) as $media) {
                $this->moveMediaToTargetModel($media, $model);
            }
        };
    }

    public function moveMediaToTargetModel(Media $media, HasMedia $target): void
    {
        $propertyName = TransientModel::getCustomPropertyName();

        $moveMediaToTargetModelCallback = callable_or_default(
            $this->moveMediaToTargetModelCallback,
            function (Media $media, HasMedia $target, string $propertyName): void {
                $media
                    ->forgetCustomProperty($propertyName)
                    ->move($target, $this->collectionName, $this->diskName)
                    ->update(['manipulations' => $media->manipulations]);
            }
        );

        $moveMediaToTargetModelCallback($media, $target, $propertyName);
    }

    public function meta(): array
    {
        return array_merge([
            'collectionName' => $this->collectionName,
            'single' => $this->single,
            'preventUpload' => $this->preventUpload,
        ], $this->meta);
    }
}
