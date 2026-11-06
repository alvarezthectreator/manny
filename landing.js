(() => {
  if (window.lucide) window.lucide.createIcons();

  const neutralizeLandingCopy = () => {
    document.querySelector("#pricing")?.remove();
    document.querySelectorAll('a[href="#pricing"]').forEach((link) => {
      link.href = "#features";
      link.textContent = "Access";
    });
    const replacements = new Map([
      ["Our Recent Clients & Partners", "Our Recent Clients & Collaborators"],
      ["Ephemeral", "Northstar Labs"],
      ["Wildcrafted", "Vertex Systems"],
      ["Codecraft_", "Codecraft AI"],
      ["Convergence", "Signal Works"],
      ["ImgCompress", "PixelForge"],
      ["Epicurious", "Atlas Analytics"],
      ["Watchtower", "Sentinel AI"],
      ["Renaissance", "Nova Labs"],
      ["ContrastAI", "Contrast AI"],
      ["Banking Reimagined for the Future You", "A clearer workspace for the future you"],
      ["Tools for better visibility, stronger habits, and a more considered relationship with your finances.", "Tools for better visibility, stronger habits, and a more considered way to organize your priorities."],
      ["Instant Account Setup", "Instant Workspace Setup"],
      ["Investment account", "Project account"],
      ["Real-Time Payments", "Real-Time Updates"],
      ["Send money", "Send update"],
      ["Mobile Banking", "Mobile Workspace"],
      ["Automated Savings", "Automated Planning"],
      ["Smart Budgeting", "Smart Planning"],
      ["Virtual Credit Cards", "Flexible Workflows"],
      ["Social Media Made Simple", "Your Work, Made Simple"],
      ["Bring the important numbers together and see where your money is moving, without the noise.", "Bring the important details together and see what needs attention, without the noise."],
      ["Let’s Start Sending Your Money", "Let’s Keep Work Moving"],
      ["Make everyday transfers easier to follow, with clear status updates and support close at hand.", "Make everyday tasks easier to follow, with clear status updates and support close at hand."],
      ["Bank Transfer", "Direct Transfer"],
      ["Select a plan that will empower your financial growth", "Select a plan that supports your next stage"],
      ["A clear start for managing your personal finances.", "A clear start for organizing your workspace."],
      ["Get started quickly and bring your financial picture together.", "Get started quickly and bring your important details together."],
      ["Keep track of transfers and everyday money movement.", "Keep track of updates and everyday activity."],
      ["Manage your finances wherever the day takes you.", "Manage your workspace wherever the day takes you."],
      ["Investment tracking", "Progress tracking"],
      ["Simple tools and a clear picture help you make confident financial decisions over time.", "Simple tools and a clear picture help you make confident decisions over time."],
      ["A clearer way to manage money, from people who use Cladvest every day.", "A clearer way to organize daily work, from people who use Cladvest every day."],
      ["Clarity for your finances, confidence for every next step.", "Clarity for your work, confidence for every next step."]
      , ["Start your free trial", "Create workspace"]
    ]);
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
      let value = node.nodeValue;
      replacements.forEach((replacement, source) => { value = value.replaceAll(source, replacement); });
      node.nodeValue = value;
    });
  };
  neutralizeLandingCopy();

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
    { name: "David Johnson", role: "Software Engineer", initials: "DJ", image: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=480&q=85", quote: "I appreciate the security features of the app, especially the two-factor authentication. It gives me peace of mind knowing my accounts are safe." },
    { name: "Amara Okafor", role: "Product Designer", initials: "AO", image: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=480&q=85", quote: "Seeing every balance and transfer in one calm place has changed how I plan my month. Sending money abroad takes seconds." },
    { name: "Michael Chen", role: "Founder", initials: "MC", image: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=480&q=85", quote: "The budgeting insights paid for themselves in the first quarter. Support has been quick every single time I needed it." },
    { name: "Sofia Alvarez", role: "Marketing Lead", initials: "SA", image: "https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=480&q=85", quote: "Virtual cards and real-time tracking make online shopping feel safe. I recommend Cladvest to everyone on my team." }
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
    const photo = document.querySelector("#t-photo");
    photo.replaceChildren();
    const initials = document.createElement("span");
    initials.textContent = testimonial.initials;
    initials.setAttribute("aria-label", `${testimonial.name}, ${testimonial.role}`);
    photo.append(initials);
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