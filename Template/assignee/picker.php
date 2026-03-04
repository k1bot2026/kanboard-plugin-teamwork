<?php
// Included from show.php — variables: $addUrl, $searchUrl, $csrfToken
?>
<div class="teamwork-picker" style="display:none;"
     data-search-url="<?= $this->text->e($searchUrl) ?>"
     data-add-url="<?= $this->text->e($addUrl) ?>"
     data-csrf="<?= $this->text->e($csrfToken) ?>">
    <input type="text"
           class="teamwork-picker-input"
           placeholder="<?= t('Add assignee, group, or team...') ?>"
           autocomplete="off">
    <ul class="teamwork-picker-results"></ul>
</div>
