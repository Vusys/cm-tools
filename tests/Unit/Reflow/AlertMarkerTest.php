<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use League\CommonMark\Node\Block\Paragraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vusys\CmTools\Reflow\AlertMarker;
use Vusys\CmTools\Support\DocumentParser;

final class AlertMarkerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function knownTypes(): iterable
    {
        yield 'NOTE' => ['NOTE'];
        yield 'TIP' => ['TIP'];
        yield 'IMPORTANT' => ['IMPORTANT'];
        yield 'WARNING' => ['WARNING'];
        yield 'CAUTION' => ['CAUTION'];
    }

    #[DataProvider('knownTypes')]
    public function test_it_splits_a_marker_leading_a_blockquotes_first_paragraph(string $type): void
    {
        $split = AlertMarker::split($this->firstParagraph("> [!{$type}]\n> Body text here."));

        $this->assertNotNull($split);
        $this->assertSame("[!{$type}]", $split[0]);
        $this->assertCount(1, $split[1]);
    }

    public function test_it_splits_a_marker_with_no_body(): void
    {
        $split = AlertMarker::split($this->firstParagraph('> [!NOTE]'));

        $this->assertNotNull($split);
        $this->assertSame('[!NOTE]', $split[0]);
        $this->assertSame([], $split[1]);
    }

    public function test_it_ignores_an_unknown_bracketed_word(): void
    {
        $this->assertNull(AlertMarker::split($this->firstParagraph("> [!DANGER]\n> Body text here.")));
    }

    public function test_it_ignores_a_marker_outside_a_blockquote(): void
    {
        $this->assertNull(AlertMarker::split($this->firstParagraph("[!NOTE]\nBody text here.")));
    }

    public function test_it_ignores_a_marker_that_is_not_the_blockquotes_first_block(): void
    {
        $document = DocumentParser::parse("> Intro paragraph.\n>\n> [!NOTE]\n> Body text here.");
        $paragraphs = [];

        foreach ($document->iterator() as $node) {
            if ($node instanceof Paragraph) {
                $paragraphs[] = $node;
            }
        }

        $this->assertCount(2, $paragraphs);
        $this->assertNull(AlertMarker::split($paragraphs[1]));
    }

    public function test_it_ignores_text_that_merely_starts_with_a_marker(): void
    {
        $this->assertNull(AlertMarker::split($this->firstParagraph('> [!NOTE] Body on the same line.')));
    }

    private function firstParagraph(string $markdown): Paragraph
    {
        foreach (DocumentParser::parse($markdown)->iterator() as $node) {
            if ($node instanceof Paragraph) {
                return $node;
            }
        }

        throw new RuntimeException("No paragraph found in: {$markdown}");
    }
}
