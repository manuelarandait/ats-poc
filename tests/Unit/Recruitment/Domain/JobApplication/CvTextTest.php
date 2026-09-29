<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\JobApplication;

use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\InvalidCvText;
use PHPUnit\Framework\TestCase;

final class CvTextTest extends TestCase
{
    public function test_it_keeps_the_original_layout_and_only_trims_the_edges(): void
    {
        $cv = CvText::fromString("\n  Jane Doe\n\n  Experience:\n  - Acme  \n");

        self::assertSame("Jane Doe\n\n  Experience:\n  - Acme", $cv->value);
    }

    public function test_it_rejects_a_blank_cv(): void
    {
        $this->expectException(InvalidCvText::class);

        CvText::fromString(" \n\t ");
    }

    public function test_it_rejects_a_cv_above_the_maximum_length(): void
    {
        $this->expectException(InvalidCvText::class);

        CvText::fromString(str_repeat('a', CvText::MAX_LENGTH + 1));
    }

    public function test_it_accepts_a_cv_of_exactly_the_maximum_length(): void
    {
        self::assertSame(CvText::MAX_LENGTH, mb_strlen(CvText::fromString(str_repeat('á', CvText::MAX_LENGTH))->value));
    }
}
