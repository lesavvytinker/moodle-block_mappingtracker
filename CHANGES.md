# Changelog

## 0.4.0 (2026-11-26)
- Mapped questions now also come from **Question Bank Competency Mapping** (`qbank_comp_ext`, on the Moodle
  Marketplace), as well as Question competencies. Either or both can be installed. A question mapped on any version
  counts as mapped, so editing a question doesn't lose it.

## 0.3.0 (2026-11-25)
- **Mapped questions** (with Question competencies, `qbank_competencies`): listed under each item with the quizzes they're
  in, and flagged when the quiz isn't linked to the item, when they're in no quiz, or when they're only drawn at random.
  They never count towards targets. Questions mapped outside the course's units get their own section.
- **To fix**: a count in the block and a list at the top of the full mapping page, with one-click fixes that link the
  quiz to the item (adding the item to the course's competencies first where needed).
- **Mapping across a category**: a page in each category's menu showing every unit's combined coverage across the
  courses that deliver it, each course's own mapping, and an export. New capability
  `block/mappingtracker:viewcategory`.

## 0.2.0 (2026-11-23)
- **Look for mapping in** (block setting): only look for the same units in other courses within this course's
  category (the default), a chosen category such as a whole term, with or without subcategories, or anywhere on the
  site. Keeps older intakes in other categories off the page. The full mapping page says where it's looking.

## 0.1.0 (2026-11-23)
- First release: the Mapping tracker block, the full mapping page with targets, other courses and shared tasks, and
  the mapping matrix export.
