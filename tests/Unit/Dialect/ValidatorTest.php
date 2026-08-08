<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Dialect\RuleSet\CommonMarkStrictRuleSet;
use Vusys\CmTools\Dialect\RuleSet\GitHubFlavoredMarkdownRuleSet;
use Vusys\CmTools\Dialect\RuleSet\JiraCloudMarkdownRuleSet;
use Vusys\CmTools\Dialect\Validator;
use Vusys\CmTools\Dialect\Violation;
use Vusys\CmTools\Support\DocumentParser;

final class ValidatorTest extends TestCase
{
    public function test_plain_prose_is_compatible_with_every_rule_set(): void
    {
        $document = DocumentParser::parse("# Title\n\nJust a normal paragraph with **bold** text.\n");
        $validator = new Validator;

        $this->assertSame([], $validator->validate($document, new CommonMarkStrictRuleSet));
        $this->assertSame([], $validator->validate($document, new GitHubFlavoredMarkdownRuleSet));
        $this->assertSame([], $validator->validate($document, new JiraCloudMarkdownRuleSet));
    }

    public function test_a_table_is_flagged_by_strict_commonmark_and_jira_but_not_github(): void
    {
        $document = DocumentParser::parse("| A | B |\n| --- | --- |\n| 1 | 2 |\n");
        $validator = new Validator;

        $this->assertNotSame([], $validator->validate($document, new CommonMarkStrictRuleSet));
        $this->assertSame([], $validator->validate($document, new GitHubFlavoredMarkdownRuleSet));
        $this->assertNotSame([], $validator->validate($document, new JiraCloudMarkdownRuleSet));
    }

    public function test_strikethrough_is_flagged_by_jira(): void
    {
        $document = DocumentParser::parse("~~gone~~\n");

        $violations = (new Validator)->validate($document, new JiraCloudMarkdownRuleSet);

        $this->assertCount(1, $violations);
        $this->assertSame(Validator::STRIKETHROUGH, $violations[0]->construct);
    }

    public function test_a_task_list_is_flagged_by_jira(): void
    {
        $document = DocumentParser::parse("- [ ] todo\n");

        $violations = (new Validator)->validate($document, new JiraCloudMarkdownRuleSet);

        $this->assertNotSame([], array_filter($violations, static fn (Violation $v): bool => $v->construct === Validator::TASK_LIST));
    }

    public function test_raw_html_is_flagged_by_jira_but_allowed_by_strict_commonmark(): void
    {
        $document = DocumentParser::parse("<div>hi</div>\n");
        $validator = new Validator;

        $this->assertSame([], $validator->validate($document, new CommonMarkStrictRuleSet));
        $this->assertNotSame([], $validator->validate($document, new JiraCloudMarkdownRuleSet));
    }

    public function test_a_disallowed_raw_html_tag_is_flagged_by_github(): void
    {
        $document = DocumentParser::parse("<script>alert(1)</script>\n");

        $violations = (new Validator)->validate($document, new GitHubFlavoredMarkdownRuleSet);

        $this->assertNotSame([], $violations);
    }

    public function test_violations_carry_a_line_number(): void
    {
        $document = DocumentParser::parse("Intro\n\n~~gone~~\n");

        $violations = (new Validator)->validate($document, new JiraCloudMarkdownRuleSet);

        $this->assertSame(3, $violations[0]->line);
    }
}
