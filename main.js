const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');
const navLinks = document.querySelectorAll('.nav-link');

// Toggle mobile menu
hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
});

// Close mobile menu when clicking on a nav link
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navMenu.classList.remove('active');
    });
});

// Add active class to current navigation item based on scroll position
window.addEventListener('scroll', () => {
    let current = '';
    const sections = document.querySelectorAll('section');
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= (sectionTop - 200)) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href').slice(1) === current) {
            link.classList.add('active');
        }
    });
});

// Smooth scrolling for navigation links
navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('href');
        const targetSection = document.querySelector(targetId);
        
        if (targetSection) {
            targetSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});


 var typed = new Typed(".typed-text", {
      strings: (window.TYPED_STRINGS && window.TYPED_STRINGS.length) ? window.TYPED_STRINGS : ["Sineth Tharaka"],
      typeSpeed: 80,
      backSpeed: 50,
      loop: true
    });

// Contact form -> contact.php (saves to database)
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const status = document.getElementById('formStatus');
        status.style.color = '#ccc';
        status.textContent = 'Sending...';
        try {
            const res = await fetch('contact.php', { method: 'POST', body: new FormData(contactForm) });
            const data = await res.json();
            if (data.ok) { status.style.color = '#00e676'; status.textContent = 'Thank you! Your message has been sent.'; contactForm.reset(); }
            else { status.style.color = '#ff6b6b'; status.textContent = data.error || 'Could not send your message.'; }
        } catch (err) { status.style.color = '#ff6b6b'; status.textContent = 'Network error. Please try again.'; }
    });
}

// Projects: show only the first 4 cards, "See more" reveals the rest
(function () {
    const LIMIT = 4;
    const box = document.querySelector('#projects .project-container');
    if (!box) return;
    const cards = Array.from(box.children).filter(el => el.classList.contains('project-card'));
    if (cards.length <= LIMIT) return;

    // remove any button added by hand in index.php so there is never a duplicate
    document.querySelectorAll('#projects .see-more-wrap').forEach(el => el.remove());

    cards.forEach((card, i) => card.classList.toggle('project-extra', i >= LIMIT));

    const wrap = document.createElement('div');
    wrap.className = 'see-more-wrap';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.id = 'projectsMore';
    btn.textContent = 'See more';
    btn.setAttribute('aria-expanded', 'false');
    wrap.appendChild(btn);
    box.insertAdjacentElement('afterend', wrap);

    btn.addEventListener('click', () => {
        box.classList.add('show-all');
        wrap.remove();
    });
})();
