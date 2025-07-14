<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\FormBundle\ListBuilder;

use Sulu\Bundle\FormBundle\Entity\Dynamic;
use Sulu\Bundle\FormBundle\Exception\InvalidListBuilderValueException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Dynamic list builder.
 */
class DynamicListBuilder implements DynamicListBuilderInterface
{
    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $downloadUrl;

    public function __construct(string $delimiter, RouterInterface $router)
    {
        $this->delimiter = $delimiter;
        $this->router = $router;
    }

    public function build(Dynamic $dynamic, string $locale): array
    {
        $entry = $dynamic->getFields();

        $singleEntry = [
            'id' => $dynamic->getId(),
        ];

        foreach ($entry as $key => $value) {
            if (Dynamic::TYPE_ATTACHMENT === $dynamic->getFieldType($key)) {
                $singleEntry[$key] = $this->getMediaUrls($value, $locale);
            } else {
                $singleEntry[$key] = $this->toString($value);
            }
        }

        $singleEntry['created'] = $dynamic->getCreated()->format('c');

        return [$singleEntry];
    }

    /**
     * Convert value to string.
     *
     * @param mixed $value
     */
    private function toString($value): string
    {
        if (\is_string($value) || \is_numeric($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTime) {
            return $value->format('c');
        }

        if (!$value) {
            return '';
        }

        if (!\is_array($value)) {
            throw new InvalidListBuilderValueException(\json_encode($value));
        }

        return \implode($this->delimiter, $value);
    }

    /**
     * @param mixed $value
     */
    private function getMediaUrls($value, string $locale): string
    {
        if (\is_string($value)) {
            return $this->getMediaUrl($value, $locale);
        }

        if (\is_array($value)) {
            foreach ($value as $key => $mediaId) {
                $value[$key] = $this->getMediaUrl($mediaId, $locale);
            }

            return \implode($this->delimiter, $value);
        }

        return $this->toString($value);
    }

    private function getMediaUrl(string $value, string $locale): string
    {
        return \str_replace(['{id}', '{locale}'], [$value, $locale], $this->getDownloadUrl());
    }

    /**
     * For performance generate route only once.
     */
    private function getDownloadUrl(): string
    {
        if (null === $this->downloadUrl) {
            // The given id must be a number which we replace
            $idReplacerNumber = '875421';
            $localeToReplace = 'en';

            $this->downloadUrl = \str_replace([$idReplacerNumber, $localeToReplace], ['{id}', '{locale}'], $this->router->generate(
                'sulu_media.redirect',
                [
                    'id' => $idReplacerNumber,
                    'locale' => $localeToReplace,
                ],
                RouterInterface::ABSOLUTE_URL
            ));
        }

        return $this->downloadUrl;
    }
}
