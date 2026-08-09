<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vusys\CmTools\Dialect\RuleSet;
use Vusys\CmTools\Dialect\RuleSet\GitHubFlavoredMarkdownRuleSet;
use Vusys\CmTools\Dialect\RuleSetRegistry;
use Vusys\CmTools\Dialect\Validator;
use Vusys\CmTools\Support\SkillAsset;

/**
 * The skill is prose, so most of it can only be reviewed by a human — but the parts a reader
 * would trust as *facts about this package* are checked here against the code that defines
 * them, because a stale dialect table is worse than no table at all.
 */
final class SkillAssetTest extends TestCase
{
    /** Claude Code truncates a skill description beyond this, and the description is all the model sees when deciding to load it. */
    private const int MAX_DESCRIPTION_LENGTH = 1024;

    public function test_the_asset_is_readable(): void
    {
        $this->assertFileExists(SkillAsset::path());
        $this->assertNotSame('', trim(SkillAsset::contents()));
    }

    public function test_the_phar_build_ships_the_asset(): void
    {
        $box = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/box.json'), true);
        $this->assertIsArray($box);

        $directories = $box['directories'] ?? null;
        $this->assertIsArray($directories);
        $this->assertContains('resources', $directories, 'box.json must bundle the skill asset, or install-skill fails only in the built phar');
    }

    public function test_the_frontmatter_names_and_describes_the_skill(): void
    {
        $frontmatter = $this->frontmatter();

        $this->assertArrayHasKey('name', $frontmatter);
        $this->assertSame(SkillAsset::NAME, $frontmatter['name'], 'the frontmatter name must match the directory the skill installs into');

        $this->assertArrayHasKey('description', $frontmatter);
        $this->assertNotSame('', trim($frontmatter['description']));
        $this->assertLessThanOrEqual(self::MAX_DESCRIPTION_LENGTH, mb_strlen($frontmatter['description']));
    }

    public function test_the_dialect_table_matches_the_rule_sets(): void
    {
        [$header, $rows] = $this->dialectTable();

        $dialects = array_map(static fn (string $cell): string => trim($cell, '`'), array_slice($header, 1));
        $registry = new RuleSetRegistry;

        $this->assertSame($registry->keys(), $dialects, 'the table must have one column per registered dialect, in registry order');

        $documented = [];

        foreach ($rows as $row) {
            $constructs = $this->backtickedWords($row[0]);
            $this->assertNotSame([], $constructs);

            foreach ($constructs as $construct) {
                $documented[] = $construct;

                foreach ($dialects as $index => $key) {
                    $ruleSet = $registry->get($key);
                    $this->assertInstanceOf(RuleSet::class, $ruleSet);

                    $cell = $row[$index + 1];
                    $saysSupported = str_contains($cell, '✓');

                    $this->assertNotSame($saysSupported, str_contains($cell, '✗'), "\"{$cell}\" must be exactly one of ✓ or ✗");
                    $this->assertSame(
                        ! in_array($construct, $ruleSet->unsupportedConstructs(), true),
                        $saysSupported,
                        "SKILL.md is out of date about {$construct} on {$key}",
                    );
                }
            }
        }

        $this->assertSame($this->everyConstruct(), $this->sorted($documented), 'the table must cover every construct the validator knows about');
    }

    public function test_the_table_footnote_lists_the_tags_github_strips(): void
    {
        $paragraph = array_find($this->paragraphs(), fn ($candidate): bool => str_starts_with($candidate, '†'));
        $this->assertIsString($paragraph);
        $this->assertSame(
            $this->sorted((new GitHubFlavoredMarkdownRuleSet)->disallowedHtmlTags()),
            $this->sorted($this->backtickedWords($paragraph)),
        );
    }

    /**
     * @return array<string, string>
     */
    private function frontmatter(): array
    {
        $contents = SkillAsset::contents();
        $this->assertStringStartsWith("---\n", $contents);

        $end = strpos($contents, "\n---\n", 4);
        $this->assertIsInt($end, 'the frontmatter block must be closed');

        $frontmatter = [];

        foreach (explode("\n", substr($contents, 4, $end - 4)) as $line) {
            if (preg_match('/^([a-z-]+):\s*(\S.*)$/', $line, $matches) === 1) {
                $frontmatter[$matches[1]] = $matches[2];
            }
        }

        return $frontmatter;
    }

    /**
     * The "What each dialect rejects" table, as its header cells plus its body rows.
     *
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function dialectTable(): array
    {
        $header = null;
        $rows = [];

        foreach (preg_split('/\R/', SkillAsset::contents()) ?: [] as $line) {
            $isRow = str_starts_with($line, '|');

            if ($header === null) {
                if ($isRow && str_contains($line, 'Construct')) {
                    $header = $this->cells($line);
                }

                continue;
            }

            if (! $isRow) {
                break;
            }

            $cells = $this->cells($line);

            if (preg_match('/^-+$/', $cells[0]) !== 1) {
                $rows[] = $cells;
            }
        }

        $this->assertIsArray($header, 'SKILL.md must contain a table with a Construct column');
        $this->assertNotSame([], $rows);

        return [$header, $rows];
    }

    /**
     * @return list<string>
     */
    private function cells(string $line): array
    {
        return array_map(trim(...), explode('|', trim($line, "| \t")));
    }

    /**
     * @return list<string>
     */
    private function backtickedWords(string $text): array
    {
        preg_match_all('/`([a-z][a-z-]*)`/', $text, $matches);

        return $matches[1];
    }

    /**
     * Every `CONSTRUCT_*`-equivalent key {@see Validator} knows, read by reflection so a new one
     * fails this test until the skill documents it.
     *
     * @return list<string>
     */
    private function everyConstruct(): array
    {
        return $this->sorted(array_filter(new ReflectionClass(Validator::class)->getConstants(), is_string(...)));
    }

    /**
     * @param  array<array-key, string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }

    /**
     * @return list<string>
     */
    private function paragraphs(): array
    {
        return preg_split('/\R{2,}/', SkillAsset::contents()) ?: [];
    }
}
