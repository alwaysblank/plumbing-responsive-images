<?php


namespace Livy\Plumbing\ResponsiveImages;


class Image
{
    protected $id;
    protected $alt;
    protected $title;
    protected $srcset;
    protected $sizes;

    protected $metadata = [];
    protected $post;
    protected $valid;


    /**
     * Image constructor.
     *
     * @param int    $id
     * @param null   $alt
     * @param null   $title
     * @param string $size
     * @param null   $sizes
     */
    public function __construct($id, $alt = null, $title = null, $size = 'medium_large', $sizes = null)
    {
        $this->id    = $id;
        $this->alt   = $alt;
        $this->title = $title;
        $this->size  = $size;
        $this->sizes = $sizes;

        $this->setup();
    }

    /**
     * Set up some properties that other methods will need.
     */
    protected function setup()
    {
        $this->post = get_post($this->id);
        if ($this->post) {
            $this->valid = ($this->post->post_type === 'attachment')
                           && (0 === strpos($this->post->post_mime_type, 'image'));
            if ($this->valid) {
                $this->metadata = $this->dot(wp_get_attachment_metadata($this->post));
            }
        }
    }

    /**
     * Convert an array into a dot-separated single-level one.
     *
     * Pulled from Illuminate\Support\Arr to avoid dependencies--I may no
     * claims to having developed this code.
     *
     * @param        $array
     * @param string $prepend
     *
     * @return array
     */
    protected function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, $this->dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get something from a dotted array, with an optional default.
     *
     * @param            $string
     * @param            $array
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    protected function dotGet($string, $array, $default = null)
    {
        if (isset($array[$string])) {
            return $array[$string];
        }

        return $default;
    }

    /**
     * Get the alt text as a string.
     *
     * @return string|null
     */
    public function alt(): ?string
    {
        if ($this->valid) {
            if (is_string($this->alt)) {
                return $this->alt;
            }

            return get_post_meta($this->post->ID, '_wp_attachment_image_alt', true);
        }

        return null;
    }

    /**
     * Get the title as a string.
     *
     * @return string|null
     */
    public function title(): ?string
    {
        if ($this->valid) {
            return $this->title ?? $this->post->post_title;
        }

        return null;
    }

    /**
     * Get the src as a string.
     *
     * @return string|null
     */
    public function src(): ?string
    {
        if ($this->valid) {
            return wp_get_attachment_image_src($this->post->ID, $this->size) ?: null;
        }

        return null;
    }

    /**
     * Get the srcset as a string.
     *
     * @return string|null
     */
    public function srcset(): ?string
    {
        if ($this->valid) {
            return wp_get_attachment_image_srcset($this->post->ID, $this->size) ?: null;
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function sizes(): ?string
    {
        if ($this->valid) {
            if (null !== $this->sizes) {
                if (is_string($this->sizes)) {
                    return $this->sizes;
                } elseif (is_array($this->sizes)) {
                    return join(', ', array_filter(array_map(function ($row) {
                        if (is_array($row) && count($row) == 2) {
                            $uri   = $row[0];
                            $query = $row[1];

                            return sprintf("%s %s", $uri, $query);
                        } elseif (is_string($row)) {
                            return $row;
                        }

                        return null;
                    }, $this->sizes)));
                } elseif (is_callable($this->sizes)) {
                    return call_user_func_array($this->sizes, [
                        'id'   => $this->post->ID,
                        'size' => $this->size,
                    ]);
                }
            }

            return wp_get_attachment_image_sizes($this->post->ID, $this->size);
        }

        return null;
    }

    /**
     * Get the height/width ratio as a float.
     *
     * @return float|null
     */
    public function ratioFloat(): ?float
    {
        if ($this->valid) {
            if ($this->dotGet("sizes.{$this->size}", $this->metadata)) {
                return $this->dotGet("sizes.{$this->size}.height",
                        $this->metadata) / $this->dotGet("sizes.{$this->size}.width", $this->metadata);
            }

            return $this->dotGet("height", $this->metadata) / $this->dotGet("width", $this->metadata);
        }

        return null;
    }

    /**
     * Get the height/width ratio as a percentage value.
     *
     * This is useful for using as bottom-padding when absolutely positioning
     * image and wanting them to retain their original ratio.
     *
     * @return float|null
     */
    public function ratioPercent(): ?float
    {
        if ($this->valid) {
            return round($this->ratioFloat() * 100, 2);
        }

        return null;
    }
}
