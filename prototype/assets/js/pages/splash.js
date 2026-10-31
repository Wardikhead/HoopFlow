import { route } from '../routes.js';

export function render({ root }) {
  root.innerHTML = `
    <div class="hf-auth-card hf-auth-card--wide" data-testid="splash-card">
      <span class="hf-eyebrow">Philippine grassroots basketball</span>
      <h1 class="hf-auth-title">Run the matchday, not the spreadsheet.</h1>
      <p class="hf-auth-sub">
        Team review, payments, brackets, court schedules, final scores, and standings
        in one courtside operations desk.
      </p>
      <div class="hf-btn-row">
        <a class="hf-btn hf-btn--primary hf-full" href="${route('login')}" data-testid="splash-enter">
          Enter the demo
        </a>
        <a class="hf-btn hf-full" href="${route('register')}" data-testid="splash-register">
          Create an account
        </a>
      </div>
      <div class="hf-flex hf-wrap hf-gap-3 hf-mt-4 hf-small hf-muted">
        <span><strong>3</strong> tournaments</span>
        <span><strong>16</strong> registered teams</span>
        <span><strong>4</strong> courts</span>
        <span><strong>10</strong> demo steps</span>
      </div>
      <p class="hf-small hf-muted hf-mt-3 hf-mb-0">
        Prototype only. Data stays in this browser; no real accounts or payments.
      </p>
    </div>
  `;
}