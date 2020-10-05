<?php

namespace Sammyjo20\Jockey\Actions;

use Carbon\Carbon;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Options;
use Sammyjo20\Jockey\Exceptions\ParsingMailableFailedException;
use Sammyjo20\Jockey\Helpers\UrlHelper;

class AppendUrlToMailableContent
{
    /**
     * @var string
     */
    protected $viewingReference;

    /**
     * @var Carbon
     */
    protected $viewingExpiry;

    /**
     * @var string
     */
    protected $messageContent;

    /**
     * AppendUrlToMailableContent constructor.
     *
     * @param string $viewingReference
     * @param Carbon $viewingExpiry
     * @param string $messageContent
     */
    public function __construct(string $viewingReference, Carbon $viewingExpiry, string $messageContent)
    {
        $this->setViewingReference($viewingReference)
            ->setViewingExpiry($viewingExpiry)
            ->setMessageContent($messageContent);
    }

    /**
     * @return string
     * @throws ParsingMailableFailedException
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\ContentLengthException
     * @throws \PHPHtmlParser\Exceptions\LogicalException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function execute(): string
    {
        $placement = config('jockey.component_placement', 'before');
        $message = $this->createDomFromHtmlString($this->messageContent);
        $bodies = $message->find('body');

        if (!$bodies->count()) {
            throw new ParsingMailableFailedException('Could not find a <body> tag in the mailable.');
        }

        $component = $this->createDomFromHtmlString($this->getComponentHtml());
        $componentChildren = array_reverse($component->getChildren());

        $body = $bodies[0];
        $bodyChildren = $body->getChildren();

        if ($placement === 'before' && count($bodyChildren) <= 0) {
            throw new ParsingMailableFailedException('There are no children inside the <body> tag.');
        }

        foreach($componentChildren as $child) {
            if ($placement === 'before') {
                $body->insertBefore($child, $bodyChildren[0]->id());
                continue;
            }

            if ($placement === 'after') {
                $body->addChild($child);
            }
        }

        return $message->outerHtml;
    }

    /**
     * @param string $string
     * @return Dom
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\ContentLengthException
     * @throws \PHPHtmlParser\Exceptions\LogicalException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    private function createDomFromHtmlString(string $string): Dom
    {
        $options = (new Options())
            ->setRemoveStyles(false)
            ->setRemoveScripts(false)
            ->setRemoveSmartyScripts(false)
            ->setRemoveDoubleSpace(false)
            ->setPreserveLineBreaks(true)
            ->setWhitespaceTextNode(true)
            ->setStrict(false);

        return (new Dom)->setOptions($options)->loadStr($string);
    }

    private function getComponentHtml(): string
    {
        $url = UrlHelper::generateOnlineVersionUrl($this->viewingReference, $this->viewingExpiry);

        return view('jockey::components.view-online', ['url' => $url])->render();
    }

    private function setViewingReference(string $value): self
    {
        $this->viewingReference = $value;

        return $this;
    }

    private function setViewingExpiry(Carbon $value): self
    {
        $this->viewingExpiry = $value;

        return $this;
    }

    private function setMessageContent(string $value): self
    {
        $this->messageContent = $value;

        return $this;
    }
}
