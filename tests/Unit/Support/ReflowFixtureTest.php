<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vusys\CmTools\Tests\Support\ReflowFixture;

/**
 * The catalogue's expectations are only as trustworthy as the loader that reads them, so the
 * loader's own failure modes are pinned here — in particular that a malformed or
 * assertion-free fixture is a loud error rather than a case that silently tests nothing.
 */
final class ReflowFixtureTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            @unlink($path);
        }

        $this->written = [];

        parent::tearDown();
    }

    public function test_it_reads_input_wrap_and_unwrap_sections(): void
    {
        $fixture = $this->load(<<<'FIXTURE'
            @@@ note
            Ignored entirely.

            @@@ input
            one two

            @@@ wrap 80
            one two

            @@@ wrap 3
            one
            two

            @@@ unwrap
            one two
            FIXTURE);

        $this->assertSame("one two\n", $fixture->input);
        $this->assertSame([3 => "one\ntwo\n", 80 => "one two\n"], $fixture->wrapExpectations);
        $this->assertSame("one two\n", $fixture->unwrapExpectation);
        $this->assertNull($fixture->htmlDriftReason);
    }

    public function test_wrap_expectations_are_ordered_by_width(): void
    {
        $fixture = $this->load("@@@ input\na\n\n@@@ wrap 80\na\n\n@@@ wrap 9\na\n\n@@@ wrap 40\na\n");

        $this->assertSame([9, 40, 80], array_keys($fixture->wrapExpectations));
    }

    public function test_it_keeps_trailing_spaces_inside_a_body_but_drops_surrounding_blank_lines(): void
    {
        $fixture = $this->load("@@@ input\n\nline one  \nline two\n\n\n@@@ unwrap\nx\n");

        $this->assertSame("line one  \nline two\n", $fixture->input);
    }

    public function test_it_keeps_blank_lines_inside_a_body(): void
    {
        $fixture = $this->load("@@@ input\nfirst\n\nsecond\n\n@@@ unwrap\nx\n");

        $this->assertSame("first\n\nsecond\n", $fixture->input);
    }

    public function test_it_records_an_html_drift_reason(): void
    {
        $fixture = $this->load("@@@ input\na\n\n@@@ unwrap\na\n\n@@@ html-drift\nThe sentinel byte is stripped.\n");

        $this->assertSame('The sentinel byte is stripped.', $fixture->htmlDriftReason);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unknown section '@@@ reflow'");

        $this->load("@@@ input\na\n\n@@@ reflow\na\n");
    }

    public function test_it_rejects_a_fixture_with_no_input(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("has no '@@@ input' section");

        $this->load("@@@ unwrap\na\n");
    }

    public function test_it_rejects_a_fixture_that_asserts_nothing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('asserts nothing');

        $this->load("@@@ input\na\n");
    }

    public function test_it_rejects_a_wrap_section_without_a_numeric_width(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('without a numeric width');

        $this->load("@@@ input\na\n\n@@@ wrap wide\na\n");
    }

    public function test_it_rejects_content_before_the_first_section(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('content before its first');

        $this->load("stray\n\n@@@ input\na\n\n@@@ unwrap\na\n");
    }

    public function test_the_catalogue_directory_holds_every_shipped_fixture(): void
    {
        $catalogue = ReflowFixture::catalogue();
        $names = array_map(static fn (ReflowFixture $fixture): string => $fixture->name, $catalogue);

        $this->assertNotEmpty($catalogue);
        $this->assertSame($names, array_unique($names));
    }

    private function load(string $contents): ReflowFixture
    {
        $path = sys_get_temp_dir().'/cm-tools-fixture-'.bin2hex(random_bytes(8)).'.fixture';
        file_put_contents($path, $contents);
        $this->written[] = $path;

        return ReflowFixture::fromFile($path);
    }
}
