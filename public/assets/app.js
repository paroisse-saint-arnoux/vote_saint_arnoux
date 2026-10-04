(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = (sel, root = document) => root.querySelector(sel);

    const fmt = (v, d = 1) => (v === null || v === undefined) ? '—' : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const heat = (v) => v === null || v === undefined ? 'heat-none' : 'heat-' + Math.max(0, Math.min(5, Math.round(v)));
    const sdClass = (v, scale = 1) => v === null || v === undefined ? '' : (v < 0.75 * scale ? 'sd-0' : (v <= 1.25 * scale ? 'sd-1' : 'sd-2'));

    // ------------------------------------------------------------------ Divers

    document.addEventListener('click', (ev) => {
        const confirmBtn = ev.target.closest('[data-confirm]');
        if (confirmBtn && !window.confirm(confirmBtn.dataset.confirm)) {
            ev.preventDefault();
            return;
        }
        const copyBtn = ev.target.closest('[data-copy]');
        if (copyBtn) {
            const input = $(copyBtn.dataset.copy);
            input.select();
            (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject())
                .catch(() => document.execCommand('copy'))
                .finally(() => { copyBtn.textContent = 'Copié ✓'; });
        }
    });

    // Menu repliable (mobile)
    const menuToggle = $('.menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            const open = menuToggle.closest('.topbar').classList.toggle('open');
            menuToggle.setAttribute('aria-expanded', String(open));
        });
    }

    // Bouton activé seulement quand le champ correspond au mot attendu (attribut pattern)
    document.querySelectorAll('[data-unlock]').forEach((input) => {
        const btn = $(input.dataset.unlock);
        input.addEventListener('input', () => { btn.disabled = input.value.trim() !== input.pattern; });
    });

    // Envoi du formulaire dès qu'une liste déroulante change (ex. tri de la page d'accueil)
    document.querySelectorAll('[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
    });

    // ------------------------------------------------------------------ Fin de notation

    /** Message de félicitations + confettis quand le dernier critère du dernier dossier est noté. */
    function celebrate() {
        const dialog = document.createElement('dialog');
        dialog.className = 'congrats';
        dialog.innerHTML = '<div class="congrats-emoji" aria-hidden="true">🎉</div>'
            + '<h2>Bravo, vous avez tout noté !</h2>'
            + '<p>Merci pour votre travail : tous les dossiers sont évalués. '
            + 'Le classement et les tableaux de bord de chaque architecte vous sont désormais accessibles.</p>'
            + '<div class="congrats-actions"><a class="btn primary" href="/classement">Voir le classement</a>'
            + '<button type="button" class="btn">Fermer</button></div>';
        dialog.querySelector('button').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => dialog.remove());
        document.body.appendChild(dialog);
        dialog.showModal();
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) confetti(dialog);
    }

    /** Confettis plein écran ; le canevas est placé dans la modale pour passer au-dessus de son fond. */
    function confetti(container) {
        const canvas = document.createElement('canvas');
        canvas.className = 'confetti';
        container.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const W = canvas.width = innerWidth * dpr, H = canvas.height = innerHeight * dpr;
        const colors = ['#7a2e3a', '#c8453b', '#e7a93b', '#6ea853', '#2f8a57', '#3b6fc8', '#d9a6b0'];
        const pieces = Array.from({ length: 180 }, (_, i) => {
            const fromLeft = i % 2 === 0;
            const angle = (fromLeft ? -60 : -120) * Math.PI / 180 + (Math.random() - .5) * .9;
            const speed = (12 + Math.random() * 14) * dpr;
            return {
                x: fromLeft ? 0 : W, y: H * .8,
                vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed,
                w: (6 + Math.random() * 6) * dpr, h: (8 + Math.random() * 8) * dpr,
                rot: Math.random() * Math.PI, vr: (Math.random() - .5) * .3,
                color: colors[i % colors.length],
            };
        });
        const start = performance.now();
        const frame = (t) => {
            ctx.clearRect(0, 0, W, H);
            const fade = Math.max(0, 1 - (t - start - 3500) / 1500);
            ctx.globalAlpha = fade;
            pieces.forEach((p) => {
                p.vy += .35 * dpr; p.vx *= .99; p.vy *= .99;
                p.x += p.vx; p.y += p.vy; p.rot += p.vr;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.scale(1, Math.cos(p.rot * 3));
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            if (fade > 0) requestAnimationFrame(frame); else canvas.remove();
        };
        requestAnimationFrame(frame);
    }

    /** Rafraîchit périodiquement des données JSON (en pause quand l'onglet est masqué). */
    function poll(url, interval, onData, onStatus) {
        let timer = null;
        const tick = async () => {
            try {
                const res = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (res.status === 401) { window.location.href = '/connexion'; return; }
                if (!res.ok) throw new Error(res.status);
                onData(await res.json());
                onStatus && onStatus(true);
            } catch (e) {
                onStatus && onStatus(false);
            }
            schedule();
        };
        const schedule = () => { clearTimeout(timer); if (!document.hidden) timer = setTimeout(tick, interval); };
        document.addEventListener('visibilitychange', () => { if (document.hidden) clearTimeout(timer); else tick(); });
        schedule();
    }

    /** Remplace le contenu d'une cellule et la fait clignoter si la valeur a changé. */
    function diffRender(table, html) {
        const previous = new Map();
        table.querySelectorAll('[data-k]').forEach((el) => previous.set(el.dataset.k, el.dataset.v));
        table.tBodies[0].innerHTML = html;
        if (previous.size === 0) return;
        table.querySelectorAll('[data-k]').forEach((el) => {
            if (previous.has(el.dataset.k) && previous.get(el.dataset.k) !== el.dataset.v) {
                el.classList.add('changed');
            }
        });
    }

    const cell = (key, value, cls, content) =>
        `<td class="${cls}" data-k="${key}" data-v="${value === null || value === undefined ? '' : value}">${content}</td>`;

    // ------------------------------------------------------------------ Grille de notation

    const scoring = $('#scoring');
    if (scoring) {
        const architectId = Number(scoring.dataset.architect);
        const weights = JSON.parse($('#weights').textContent);
        const state = $('#save-state');
        const totalEl = $('#my-total');
        let pending = 0;

        const computeTotal = () => {
            let sum = 0, w = 0;
            scoring.querySelectorAll('fieldset.criterion').forEach((fs) => {
                const checked = fs.querySelector('input:checked');
                if (checked && checked.value !== '') {
                    const weight = weights[fs.dataset.criterion];
                    sum += weight * Number(checked.value);
                    w += weight;
                }
                fs.classList.toggle('is-scored', !!(checked && checked.value !== ''));
            });
            totalEl.textContent = w ? fmt(sum / w * 20) : '—';
        };

        const setState = (text, cls) => { state.textContent = text; state.className = 'save-state ' + (cls || ''); };

        scoring.addEventListener('change', async (ev) => {
            const input = ev.target;
            if (input.type !== 'radio') return;
            const fs = input.closest('fieldset');
            computeTotal();
            pending++;
            setState('Enregistrement…', 'saving');
            try {
                const res = await fetch('/api/score', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({
                        architect_id: architectId,
                        criterion: Number(fs.dataset.criterion),
                        score: input.value === '' ? null : Number(input.value),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Erreur ' + res.status);
                if (--pending === 0) {
                    const now = new Date();
                    setState('✓ Enregistré à ' + now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }), 'saved');
                }
                fs.classList.remove('flash-ok');
                void fs.offsetWidth;
                fs.classList.add('flash-ok');
                if (data.completed) celebrate();
            } catch (e) {
                pending--;
                setState('⚠ Non enregistré : ' + e.message, 'error');
            }
        });

        computeTotal();

        // Nom de l'agence dans la barre collante une fois l'en-tête du dossier sorti de l'écran
        const header = $('.architect-header');
        if (header && 'IntersectionObserver' in window) {
            const summary = $('.scoring-summary');
            new IntersectionObserver(([entry]) => {
                summary.classList.toggle('stuck', !entry.isIntersecting && entry.boundingClientRect.top < 0);
            }, { rootMargin: '-60px 0px 0px 0px' }).observe(header);
        }
    }

    // ------------------------------------------------------------------ Classement

    const ranking = $('#ranking');
    if (ranking) {
        const criteria = JSON.parse(ranking.dataset.criteria);
        const keys = Object.keys(criteria);
        const voters = Number(ranking.dataset.voters);

        ranking.tHead.innerHTML = '<tr><th class="rank">Rang</th><th>Agence</th><th class="num">Score / 100</th>'
            + keys.map((k) => `<th class="num" title="${esc(criteria[k].title)}">C${k}<small>${esc(criteria[k].short)} · ${criteria[k].weight} %</small></th>`).join('')
            + '<th class="num" title="Votants ayant noté les 6 critères">Votes complets</th></tr>';

        const render = (rows) => {
            let rank = 0;
            diffRender(ranking, rows.map((r) => {
                const ranked = r.total !== null;
                if (ranked) rank++;
                return `<tr class="clickable ${ranked ? '' : 'unranked'}" data-href="/architecte/${r.id}/tableau">`
                    + `<td class="rank">${ranked ? rank : '–'}</td>`
                    + `<td><a href="/architecte/${r.id}/tableau">${esc(r.agency)}</a><small class="muted"> ${esc(r.city || '')}</small></td>`
                    + cell(`t${r.id}`, r.total, 'num total', ranked
                        ? `<span class="bar"><i style="width:${r.total}%"></i></span><b>${fmt(r.total)}</b>` : '—')
                    + keys.map((k) => cell(`c${r.id}-${k}`, r.criteria[k], 'num ' + heat(r.criteria[k]), fmt(r.criteria[k], 2))).join('')
                    + cell(`n${r.id}`, r.complete, 'num muted', `${r.complete} / ${voters}`)
                    + '</tr>';
            }).join(''));
        };

        ranking.addEventListener('click', (ev) => {
            const tr = ev.target.closest('tr[data-href]');
            if (tr && !ev.target.closest('a')) window.location.href = tr.dataset.href;
        });

        render(JSON.parse($('#ranking-data').textContent));
        poll(ranking.dataset.src, 10000, render);
    }

    // ------------------------------------------------------------------ Tableau de bord

    const dashboard = $('#dashboard');
    if (dashboard) {
        const criteria = JSON.parse(dashboard.dataset.criteria);
        const keys = Object.keys(criteria);
        const me = Number(dashboard.dataset.me);
        const live = $('#live');

        dashboard.tHead.innerHTML = '<tr><th></th><th class="num">Score / 100</th>'
            + keys.map((k) => `<th class="num" title="${esc(criteria[k].title)}">C${k}<small>${esc(criteria[k].short)} · ${criteria[k].weight} %</small></th>`).join('')
            + '</tr>';

        const summaryRow = (label, cls, key, data, decimals) =>
            `<tr class="${cls}"><th>${label}</th>`
            + cell(`${key}-t`, data.total, 'num total', `<b>${fmt(data.total)}</b>`)
            + keys.map((k) => cell(`${key}-${k}`, data.criteria[k], 'num ' + heat(data.criteria[k]), fmt(data.criteria[k], decimals))).join('')
            + '</tr>';

        const render = (d) => {
            const complete = d.rows.filter((r) => r.role === 'votant' && r.answered === keys.length).length;
            $('#kpi-total').textContent = fmt(d.official.total);
            $('#kpi-all').textContent = fmt(d.all.total);
            $('#kpi-complete').textContent = `${complete} / ${d.voters}`;

            let html = summaryRow('Moyenne des votants <small>score officiel</small>', 'summary official', 'o', d.official, 2)
                + summaryRow('Moyenne du jury <small>votants + consultatifs</small>', 'summary', 'a', d.all, 2)
                + '<tr class="summary stddev"><th>Écart type <small>votants + consultatifs</small></th>'
                + cell('sd-t', d.stddev.total, 'num ' + sdClass(d.stddev.total, 20), fmt(d.stddev.total))
                + keys.map((k) => cell(`sd-${k}`, d.stddev.criteria[k], 'num ' + sdClass(d.stddev.criteria[k]), fmt(d.stddev.criteria[k], 2))).join('')
                + '</tr><tr class="spacer"><td colspan="' + (keys.length + 2) + '"></td></tr>';

            html += d.rows.map((r) => {
                const partial = r.answered > 0 && r.answered < keys.length;
                const status = r.answered === 0 ? '<span class="tag wait">en attente</span>'
                    : (partial ? `<span class="tag partial">partiel ${r.answered}/${keys.length}</span>` : '');
                return `<tr class="member role-${r.role} ${r.id === me ? 'me' : ''} ${r.answered === 0 ? 'empty' : ''}">`
                    + `<th>${esc(r.name)} ${r.role === 'consultatif' ? '<span class="tag consult">consultatif</span>' : ''} ${status}</th>`
                    + cell(`m${r.id}-t`, r.total, 'num total', fmt(r.total))
                    + keys.map((k) => {
                        const v = r.scores[k];
                        return cell(`m${r.id}-${k}`, v, 'num', v === undefined ? '<span class="muted">·</span>' : `<span class="chip ${heat(v)}">${v}</span>`);
                    }).join('')
                    + '</tr>';
            }).join('');
            diffRender(dashboard, html);
        };

        render(JSON.parse($('#dashboard-data').textContent));
        poll(dashboard.dataset.src, 3000, render, (ok) => {
            live.classList.toggle('off', !ok);
            live.textContent = ok ? '● en direct' : '● connexion perdue';
        });
    }
})();
