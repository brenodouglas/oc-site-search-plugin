<?php
namespace OFFLINE\SiteSearch\Classes\Providers;

use Cms\Classes\Controller;
use Illuminate\Database\Eloquent\Collection;
use OFFLINE\SiteSearch\Classes\Result;
use OFFLINE\SiteSearch\Models\Settings;
use RainLab\Blog\Models\Post;

/**
 * Searches the contents generated by the
 * Rainlab.Blog plugin
 *
 * @package OFFLINE\SiteSearch\Classes\Providers
 */
class RainlabBlogResultsProvider extends ResultsProvider
{

    /**
     * @var Controller to be used to form urls to search results
     */
    protected $controller;

    /**
     * ResultsProvider constructor.
     *
     * @param                         $query
     * @param \Cms\Classes\Controller $controller
     */
    public function __construct($query, Controller $controller)
    {
        parent::__construct($query);
        $this->controller = $controller;
    }

    /**
     * Runs the search for this provider.
     *
     * @return ResultsProvider
     */
    public function search()
    {
        if ( ! $this->isInstalledAndEnabled()) {
            return $this;
        }

        foreach ($this->posts() as $post) {
            // Make this result more relevant, if the query is found in the title
            $relevance = mb_stripos($post->title, $this->query) === false ? 1 : 2;

            $result        = new Result($this->query, $relevance);
            $result->title = $post->title;
            $result->text  = $post->summary;
            $result->meta  = $post->created_at;

            // Maintain compatibility with old setting
            if (Settings::get('rainlab_blog_page') !== null) {
                $result->url = $post->setUrl(Settings::get('rainlab_blog_page', ''), $this->controller);
            } else {
                $result->url = $this->getUrl($post);
            }

            $result->thumb = $this->getThumb($post->featured_images);

            $this->addResult($result);
        }

        return $this;
    }

    /**
     * Get all posts with matching title or content.
     *
     * @return Collection
     */
    protected function posts()
    {
        return Post::isPublished()
                   ->with(['featured_images'])
                    ->where(function ($query) {
                        $query->where('title', 'like', "%{$this->query}%")
                            ->orWhere('content', 'like', "%{$this->query}%")
                            ->orWhere('excerpt', 'like', "%{$this->query}%");
                    })
                   ->orderBy('published_at', 'desc')
                   ->get();
    }

    /**
     * Checks if the RainLab.Blog Plugin is installed and
     * enabled in the config.
     *
     * @return bool
     */
    protected function isInstalledAndEnabled()
    {
        return $this->isPluginAvailable($this->identifier)
        && Settings::get('rainlab_blog_enabled', true);
    }

    /**
     * Generates the url to a blog post.
     *
     * @param $post
     *
     * @return string
     */
    protected function getUrl($post)
    {
        $url = trim(Settings::get('rainlab_blog_posturl', '/blog/post'), '/');

        return implode('/', [$url, $post->slug]);
    }

    /**
     * Display name for this provider.
     *
     * @return mixed
     */
    public function displayName()
    {
        return Settings::get('rainlab_blog_label', 'Blog');
    }

    /**
     * Returns the plugin's identifier string.
     *
     * @return string
     */
    public function identifier()
    {
        return 'RainLab.Blog';
    }
}

