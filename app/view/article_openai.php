<?php declare(strict_types=1); ?>
<div class="modal fade" id="articleOpenaiModal" tabindex="-1" aria-labelledby="articleOpenaiTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header text-bg-primary">
                <h2 class="modal-title fs-5" id="articleOpenaiTitle">記事をGPTに聞く</h2>
                <button type="button" class="btn-close" data-bs-theme="dark" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold text-break mb-1" data-article-openai-title></p>
                <p class="small text-break mb-2" data-article-openai-url></p>
                <details class="mb-3" data-article-openai-details hidden>
                    <summary>GPTに渡すRSSの概要</summary>
                    <pre class="small font-monospace text-wrap text-break mt-2 mb-0" data-article-openai-summary></pre>
                </details>
                <p class="small text-muted" data-article-openai-title-only hidden>タイトルとURLを送信します。この記事の概要はありません。</p>
                <form data-article-openai-form>
                    <label class="form-label" for="articleOpenaiQuestion">質問</label>
                    <textarea class="form-control" id="articleOpenaiQuestion" rows="3" maxlength="4000" required></textarea>
                    <button type="submit" class="btn btn-primary mt-2">送信</button>
                </form>
                <p class="small mt-2 mb-0" data-article-openai-status role="status"></p>
                <section class="border-top pt-3 mt-3" aria-labelledby="articleOpenaiAnswerTitle" data-article-openai-result hidden>
                    <h3 class="fs-6" id="articleOpenaiAnswerTitle">GPTの回答</h3>
                    <pre class="font-monospace text-wrap text-break mb-0" data-article-openai-answer></pre>
                </section>
            </div>
        </div>
    </div>
</div>
