# Mapping tracker (block_mappingtracker)

A Moodle block for the people who build courses. It shows, as you build, how many activities are mapped to each
item of the units the course delivers (elements, performance criteria, performance evidence, knowledge evidence) and
whether each item meets its target, so you know the course will collect enough evidence before learners start.

It is the first half of the evidence story: Mapping tracker checks the design; Evidence Reporter
(`report_evidencereporter`) reports the evidence once learners are assessed. Each works without the other.

## What it shows

- **The block** (course page, staff only): each unit with a bar and "27 of 35 met · 8 not mapped", and its gaps,
  not-mapped first, each linked to its row on the full page.
- **Full mapping** (*Open full mapping*): every item of every unit as a tree, with:
  - the activities mapped to it, as links; hidden activities are labelled
  - **Shared with…** on activities that also count for another unit (clustered delivery)
  - **Also mapped in…**: the same item mapped in other courses (an RPL course, a course delivering the rest of a split
    unit), shown but not counted unless you choose that course
  - its count against its target ("1 of 2"), and where the target came from
  - a warning when an item hasn't been added to the course's competencies, so nothing can be mapped to it yet
- **Mapping matrix export** per unit (CSV, Excel, ODS, HTML, PDF, JSON): items down the side, activities across the
  top, with counts, targets and status. The mapping document auditors and validators ask for, generated from the
  course itself.

Moodle links competencies to whole activities, so one instance of mapping is one activity linked to the item.

## Mapped questions

With **Question competencies** (`qbank_competencies`, Moodle 5.0+) installed, questions mapped to competencies in the
question bank are listed under each item on the full mapping page: the question (with a preview link), the quizzes it's
in, and flags:

- **Quiz not linked**: a quiz uses the question but doesn't have the item in its own competency settings, so the quiz
  gives no evidence for it. The most important one to fix.
- **Not in a quiz**: the question is mapped but no quiz in the course uses it.
- **Random only**: it's only drawn at random, so not every learner gets it.

Questions are supporting evidence and **never count towards a target**; only the quiz, linked to the item, does.
Questions mapped to competencies outside the course's units are listed in their own section.

**To fix**: the block shows a count (*⚠ 3 to fix*) and lists the items, and the full mapping page lists every fix at the
top. People who can manage course competencies get a button for each: *Link Quiz to this item*, or *Add to course and
to Quiz* when the item isn't one of the course's competencies yet.

## Across a category

*Mapping tracker* in a category's menu (capability `block/mappingtracker:viewcategory`, Manager by default, at
category level so a compliance team needn't be enrolled in every course):

- **Units**: every unit delivered in the category (and its subcategories, which you can switch off), the courses that
  deliver it, and its **combined** coverage: an item is met when those courses map enough activities to it between
  them, for units split across courses. Gaps and the number to fix.
- **Each course's own mapping**: per course and unit, items met from its own activities, gaps and the number to fix,
  linked to the course's full mapping.
- **Export** of both (CSV, Excel and the other usual formats).

## Targets

An item's target is, in order:

1. **Set by a manager** on the full mapping page (capability `block/mappingtracker:settargets`, Manager by default).
   A target belongs to the item, so it applies wherever the unit is delivered. *Reset* goes back to 2 or 3.
2. **From its wording**: "at least two layouts" gives 2 (setting *Suggest targets from wording*).
3. **The default** for items with nothing below them (setting *Default target*, 1). Parents (units, elements)
   summarise their children instead, unless a manager gives them a target.

## Other courses

Moodle doesn't know which courses make up a qualification, only which courses link which competencies. In the block's
settings:

- **Look for mapping in**: where to look for the same units in other courses. By default, this course's category and
  its subcategories, so older intakes kept in their own categories (e.g. *Term 4 2026*) don't crowd the page. Choose
  a broader category (e.g. *Term 4 2027*, with each qualification as a subcategory) or *Anywhere on the site*.
- **Count mapping from these courses**: mapping found there is shown, but counts towards targets only for the courses
  chosen here, e.g. when one unit is delivered across two courses. A chosen course always counts, wherever it is.

Hidden courses and courses that have ended are always left out.

## Settings

*Site administration → Plugins → Blocks → Mapping tracker*: default target, suggest targets from wording, and
whether hidden activities count (on by default, since courses are usually built with activities hidden).

## Requirements

Moodle 4.5 to 5.2, with competencies enabled. Mapped questions need Question competencies (`qbank_competencies`),
which needs Moodle 5.0 or later; without it, everything else works.

## Licence

GNU GPL v3 or later. Copyright 2026 Daniel Maloney.
