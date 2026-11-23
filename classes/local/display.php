<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_mappingtracker\local;

use html_writer;
use moodle_url;

/**
 * HTML for the block and the full mapping page.
 *
 * @package    block_mappingtracker
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display {
    /** Gaps listed per unit in the block. */
    public const BLOCK_GAPS = 5;

    /**
     * A status badge: "1 of 2".
     *
     * @param \stdClass $node
     * @return string
     */
    public static function badge(\stdClass $node): string {
        $classes = [
            coverage::MET => 'bg-success',
            coverage::UNDER => 'bg-warning text-dark',
            coverage::MISSING => 'bg-danger',
            coverage::NOTARGET => 'bg-light text-dark border',
        ];
        $text = $node->status === coverage::NOTARGET ? (string) $node->count
            : get_string('countoftarget', 'block_mappingtracker', ['count' => $node->count, 'target' => $node->target]);
        return html_writer::span(
            $text,
            'badge ' . $classes[$node->status] . ' block_mappingtracker-badge',
            ['title' => get_string('status_' . $node->status, 'block_mappingtracker')]
        );
    }

    /**
     * The one-line totals for a unit.
     *
     * @param \stdClass $unit
     * @return string
     */
    public static function totals(\stdClass $unit): string {
        $t = $unit->totals;
        $parts = [get_string('totalsmet', 'block_mappingtracker', ['met' => $t->met, 'items' => $t->items])];
        if ($t->under) {
            $parts[] = get_string('totalsunder', 'block_mappingtracker', $t->under);
        }
        if ($t->missing) {
            $parts[] = get_string('totalsmissing', 'block_mappingtracker', $t->missing);
        }
        return implode(' · ', $parts);
    }

    /**
     * A progress bar of items met.
     *
     * @param \stdClass $unit
     * @return string
     */
    public static function bar(\stdClass $unit): string {
        $t = $unit->totals;
        if (!$t->items) {
            return '';
        }
        $segments = '';
        foreach ([coverage::MET => 'bg-success', coverage::UNDER => 'bg-warning', coverage::MISSING => 'bg-danger'] as $s => $c) {
            if ($t->$s) {
                $segments .= html_writer::div('', 'progress-bar ' . $c, ['style' => 'width: ' .
                    round(100 * $t->$s / $t->items, 2) . '%', 'role' => 'presentation']);
            }
        }
        return html_writer::div($segments, 'progress block_mappingtracker-bar', ['aria-hidden' => 'true']);
    }

    /**
     * An item's name, without HTML.
     *
     * @param \stdClass $node
     * @param bool $full Where the short name was cut short (it ends in an ellipsis), the full wording from the
     *     description, if that starts the same way
     * @return string
     */
    public static function name(\stdClass $node, bool $full = false): string {
        $name = format_string($node->competency->shortname, true, ['context' => \context_system::instance()]);
        if ($full && str_ends_with($name, '…')) {
            $description = trim(html_entity_decode(strip_tags((string) $node->competency->description), ENT_QUOTES));
            $start = mb_substr($name, 0, -1);
            if (str_starts_with($description, $start)) {
                return $description;
            }
        }
        return $name;
    }

    /**
     * The block's content.
     *
     * @param int $courseid
     * @param \stdClass[] $units From {@see coverage::for_course()}
     * @return string
     */
    public static function block(int $courseid, array $units): string {
        $out = '';
        $page = new moodle_url('/blocks/mappingtracker/view.php', ['courseid' => $courseid]);
        foreach ($units as $unitid => $unit) {
            $unitout = html_writer::tag('h6', html_writer::link(
                new moodle_url($page, [], 'unit-' . $unitid),
                format_string($unit->competency->shortname)
            ), ['class' => 'mb-1']);
            $unitout .= self::bar($unit);
            $unitout .= html_writer::div(self::totals($unit), 'small text-muted mb-1');
            $gaps = coverage::gaps($unit);
            if ($gaps) {
                $items = '';
                foreach (array_slice($gaps, 0, self::BLOCK_GAPS) as $node) {
                    $items .= html_writer::tag('li', self::badge($node) . ' ' . html_writer::link(
                        new moodle_url($page, [], 'item-' . $node->competency->id),
                        shorten_text(self::name($node), 60)
                    ));
                }
                if (count($gaps) > self::BLOCK_GAPS) {
                    $items .= html_writer::tag('li', html_writer::link(
                        new moodle_url($page, [], 'unit-' . $unitid),
                        get_string('andmore', 'block_mappingtracker', count($gaps) - self::BLOCK_GAPS)
                    ), ['class' => 'small']);
                }
                $unitout .= html_writer::tag('ul', $items, ['class' => 'list-unstyled small block_mappingtracker-gaps']);
            } else if ($unit->totals->items) {
                $unitout .= html_writer::div(get_string('allmet', 'block_mappingtracker'), 'small text-success');
            }
            $out .= html_writer::div($unitout, 'block_mappingtracker-unit mb-3');
        }
        return $out;
    }

    /**
     * The activities mapped to an item, linked, with hidden and shared markers.
     *
     * @param \stdClass[] $activities
     * @param bool $showcounted Mark which count (for other courses)
     * @return string
     */
    public static function activities(array $activities, bool $showcounted = false): string {
        $items = '';
        foreach ($activities as $activity) {
            $name = $activity->url ? html_writer::link($activity->url, $activity->name) : s($activity->name);
            $extra = html_writer::span(' (' . $activity->modtitle . ')', 'text-muted');
            if (!$activity->visible) {
                $extra .= ' ' . html_writer::span(get_string('hidden', 'block_mappingtracker'), 'badge bg-secondary');
            }
            if (!empty($activity->sharedwith)) {
                $extra .= ' ' . html_writer::span(get_string(
                    'sharedwith',
                    'block_mappingtracker',
                    implode(', ', $activity->sharedwith)
                ), 'badge bg-info text-dark');
            }
            if ($showcounted && !$activity->counted) {
                $extra .= ' ' . html_writer::span(get_string('notcounted', 'block_mappingtracker'), 'text-muted');
            }
            $items .= html_writer::tag('li', $name . $extra);
        }
        return $items ? html_writer::tag('ul', $items, ['class' => 'list-unstyled mb-0']) : '';
    }
}
