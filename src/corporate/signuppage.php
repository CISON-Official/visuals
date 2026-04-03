<?php

function signup_page_shortcode()
{
    ob_start();
    ?>
    <style>
        :root {
            --bg: #93e990;
            --panel: #7cfa5d;
            --accent: #0d5204;
            --text: #ffffffdd;
        }

        * {
            box-sizing: border-box;
            margin: 0;
        }

        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
            width: 100vw;
        }

        .panel {
            --photo: none;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: var(--panel);
            border: 1px solid rgba(255, 255, 255, .1);
            position: relative;
            overflow: hidden;
            transition: filter .4s ease, transform .4s ease, background .4s ease, opacity .4s ease;
            width: 100%;
        }

        .panel::before,
        .panel::after {
            position: absolute;
            inset: 0;
            content: '';
            pointer-events: none;
        }

        .panel::before {
            background-image: var(--photo);
            background-size: cover;
            background-position: center;
            filter: brightness(.75) saturate(1.05);
            transition: filter .4s ease, opacity .4s ease;
        }

        .panel::after {
            background: linear-gradient(180deg, rgba(5, 8, 22, 0) 0, rgba(5, 8, 22, .8) 100%);
            opacity: .5;
            transition: opacity .4s ease;
        }

        .panel>* {
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
            color: rgba(255, 255, 255, .7);
        }

        .panel button {
            border: none;
            padding: .9rem 2.2rem;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            transition: transform .3s ease, box-shadow .3s ease;
        }

        .panel button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(108, 99, 255, .4);
        }

        .individual:hover .panel {
            filter: blur(2px);
            opacity: .7;
        }

        .panel:hover {
            filter: none;
            opacity: 1;
            background: var(--panel);
            transform: scale(1.02);
        }

        .panel:hover::before {
            filter: brightness(1.05) saturate(1.1);
        }

        .panel:hover::after {
            opacity: .35;
        }

        .panel:hover~.panel,
        .panel:hover+.panel,
        .panel:not(:hover) {
            pointer-events: auto;
        }

        .panel.corporate {
            --photo: url('https://images.unsplash.com/low-angle-photography-of-four-high-rise-buildings-Nl_FMFpXo2g?auto=format&fit=crop&w=1600&q=80');
        }

        .individual {
            background-image: url('https://images.unsplash.com/person-holding-phone-near-laptop-and-tablet-on-desk-7R3PqLcVnzQ?auto=format&fit=crop&w=1600&q=80');
        }

        #page,
        #content,
        .site,
        #wpadminbar,
        #primary,
        .container,
        #main,
        article,
        .entry-content {
            margin: 0em;
            padding: 0em;
            width: 100vw;
        }

        @media (max-width:880px) {
            .column {
                flex-direction: column;
            }

            .panel {
                min-height: 50vh;
                padding: 2rem;
            }
        }


        #content,
        .site,
        {
        width: 100vw;
        flex: 1;
        }

        header,
        footer,
        #wpadminbar,
        .entry-footer,
        .entry-header {
            display: none;
            height: 0px;
        }
    </style>
    <div class="container">
        <section class="panel corporate" aria-label="Corporate login">
            <h1>Corporate Login</h1>
            <p>Securely sign in with your business account to manage teams, billing, and dedicated support.</p>
            <button type="button">Corporate Access</button>
        </section>
        <section class="panel individual" aria-label="Individual login">
            <h1>Individual Login</h1>
            <p>Access your personal dashboard, track progress, and update preferences anytime.</p>
            <button type="button">Personal Access</button>
        </section>
    </div>

    <?php
    return ob_get_clean();

}

add_shortcode(
    'signup_page_shortcode',
    'signup_page_shortcode'
);
