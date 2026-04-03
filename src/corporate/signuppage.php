<?php

function signup_page_shortcode()
{
    ob_start();
    ?>
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
    }

    body {
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        min-height: 100vh;
    }

    .container {
        display: flex;
        min-height: 100vh;
        width: 100vw;
    }

    .panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        position: relative;
        overflow: hidden;
        transition: filter .4s ease, transform .4s ease, opacity .4s ease;
        width: 100%;
    }

    /* Corporate — deep forest green */
    .panel.corporate {
        --panel-bg: #0d2b1f;
        --panel-btn: #16a34a;
        --panel-btn-glow: rgba(22, 163, 74, 0.4);
        --panel-text: #f0fdf4;
        --panel-sub: rgba(240, 253, 244, 0.65);
        --photo: url('https://images.unsplash.com/low-angle-photography-of-four-high-rise-buildings-Nl_FMFpXo2g?auto=format&fit=crop&w=1600&q=80');
        background: var(--panel-bg);
        color: var(--panel-text);
    }

    /* Member — deep indigo */
    .panel.individual {
        --panel-bg: #1a1a2e;
        --panel-btn: #4f46e5;
        --panel-btn-glow: rgba(79, 70, 229, 0.4);
        --panel-text: #f8f9ff;
        --panel-sub: rgba(248, 249, 255, 0.65);
        background: var(--panel-bg);
        background-image: url('https://images.unsplash.com/person-holding-phone-near-laptop-and-tablet-on-desk-7R3PqLcVnzQ?auto=format&fit=crop&w=1600&q=80');
        background-size: cover;
        background-position: center;
        color: var(--panel-text);
    }

    .panel::before,
    .panel::after {
        position: absolute;
        inset: 0;
        content: '';
        pointer-events: none;
    }

    .panel.corporate::before {
        background-image: var(--photo);
        background-size: cover;
        background-position: center;
        filter: brightness(.6) saturate(1.05);
        transition: filter .4s ease, opacity .4s ease;
    }

    .panel::after {
        background: linear-gradient(180deg, rgba(5, 8, 22, 0) 0, rgba(5, 8, 22, .75) 100%);
        opacity: .5;
        transition: opacity .4s ease;
    }

    .panel > * {
        position: relative;
        z-index: 1;
    }

    .panel h1 {
        margin-bottom: .5rem;
        font-size: clamp(2rem, 2.5vw, 2.5rem);
    }

    .panel p {
        max-width: 20rem;
        margin-bottom: 1.5rem;
        color: var(--panel-sub);
    }

    .panel a {
        border: none;
        padding: .9rem 2.2rem;
        border-radius: 999px;
        background: var(--panel-btn);
        color: #fff;
        font-size: 1rem;
        cursor: pointer;
        text-decoration: none;
        transition: transform .3s ease, box-shadow .3s ease;
    }

    .panel a:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px var(--panel-btn-glow);
    }

    .panel:hover {
        filter: none;
        opacity: 1;
        transform: scale(1.02);
    }

    .panel:hover::before {
        filter: brightness(.85) saturate(1.1);
    }

    .panel:hover::after {
        opacity: .3;
    }

    .container:has(.panel:hover) .panel:not(:hover) {
        filter: blur(2px);
        opacity: .65;
    }

    #page, #content, .site, #wpadminbar, #primary,
    .container, #main, article, .entry-content {
        margin: 0;
        padding: 0;
        width: 100vw;
    }

    @media (max-width: 880px) {
        .container {
            flex-direction: column;
        }
        .panel {
            min-height: 50vh;
            padding: 2rem;
        }
    }

    header, footer, #wpadminbar, .entry-footer, .entry-header {
        display: none;
        height: 0px;
    }
</style>
    <div class="container">
        <section class="panel corporate" aria-label="Corporate login">
            <h1>Corporate Signup</h1>
            <p>Securely sign in with your business account to manage teams, billing, and dedicated support.</p>
            <a type="button" href="https://my.cison.org.ng/corporate-registration-2/">Signup</a>
        </section>
        <section class="panel individual" aria-label="Individual login">
            <h1>Member Signup</h1>
            <p>Access your personal dashboard, track progress, and update preferences anytime.</p>
            <a type="button" href="https://my.cison.org.ng/member-registration/">Signup</a>
        </section>
    </div>

    <?php
    return ob_get_clean();

}

add_shortcode(
    'signup_page_shortcode',
    'signup_page_shortcode'
);
