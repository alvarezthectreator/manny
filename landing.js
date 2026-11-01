(() => {
  if (window.lucide) window.lucide.createIcons();

  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!reduceMotion) {
    const revealTargets = document.querySelectorAll(
      ".hero .rating, .hero h1, .hero .hero-sub, .hero-btns, .dash, .partners > *, .f-card, .split > *, .send .point, .pricing .tag, .pricing .h2, .pricing .lead, .toggle, .plan, .testi, .dots, .know-head, .k-card, .foot-grid > *"
    );
    revealTargets.forEach((element, index) => {
      element.classList.add("reveal");
      element.style.setProperty("--reveal-delay", `${(index % 4) * 65}ms`);
    });

    if ("IntersectionObserver" in window) {
      const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          currentObserver.unobserve(entry.target);
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -35px 0px" });
      revealTargets.forEach((element) => observer.observe(element));
    } else {
      revealTargets.forEach((element) => element.classList.add("is-visible"));
    }
  }

  const toggleButtons = document.querySelectorAll(".toggle button");
  toggleButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const yearly = button.dataset.billing === "yearly";
      toggleButtons.forEach((option) => {
        const selected = option === button;
        option.classList.toggle("on", selected);
        option.setAttribute("aria-pressed", String(selected));
      });
      document.querySelectorAll(".price strong").forEach((price) => {
        const monthly = Number(price.dataset.monthly);
        price.textContent = `$${yearly ? Math.round(monthly * 0.83) : monthly}`;
        price.nextElementSibling.textContent = yearly ? " / month, billed yearly" : " / per month";
      });
    });
  });

  const testimonials = [
    { name: "David Johnson", role: "Software Engineer", initials: "DJ", quote: "I appreciate the security features of the app, especially the two-factor authentication. It gives me peace of mind knowing my accounts are safe." },
    { name: "Amara Okafor", role: "Product Designer", initials: "AO", quote: "Seeing every balance and transfer in one calm place has changed how I plan my month. Sending money abroad takes seconds." },
    { name: "Michael Chen", role: "Founder", initials: "MC", quote: "The budgeting insights paid for themselves in the first quarter. Support has been quick every single time I needed it." },
    { name: "Sofia Alvarez", role: "Marketing Lead", initials: "SA", quote: "Virtual cards and real-time tracking make online shopping feel safe. I recommend Cladvest to everyone on my team." }
  ];
  const dots = document.querySelector("#t-dots");
  if (!dots) return;

  let current = 0;
  testimonials.forEach((testimonial, index) => {
    const dot = document.createElement("button");
    dot.type = "button";
    dot.setAttribute("aria-label", `Show testimonial from ${testimonial.name}`);
    dot.addEventListener("click", () => show(index));
    dots.append(dot);
  });

  function show(index) {
    current = (index + testimonials.length) % testimonials.length;
    const testimonial = testimonials[current];
    document.querySelector("#t-quote").textContent = `“${testimonial.quote}”`;
    document.querySelector("#t-name").textContent = testimonial.name;
    document.querySelector("#t-role").textContent = testimonial.role;
    document.querySelector("#t-photo span").textContent = testimonial.initials;
    [...dots.children].forEach((dot, dotIndex) => {
      const selected = dotIndex === current;
      dot.classList.toggle("on", selected);
      dot.setAttribute("aria-current", String(selected));
    });
  }

  document.querySelectorAll(".arrow").forEach((arrow) => {
    arrow.addEventListener("click", () => show(current + Number(arrow.dataset.dir)));
  });
  show(0);
})();