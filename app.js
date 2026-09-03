/**
 * NEX - 47 CATALYS'S - Core Interactive Engine
 * Handles ROI Calculator, Service Filters, Database AJAX Lead Storage & WhatsApp Routing
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Menu Toggle
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const closeMobileMenu = document.getElementById('closeMobileMenu');
  const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

  if (mobileMenuBtn && mobileDrawer) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileDrawer.classList.remove('translate-x-full');
      document.body.classList.add('overflow-hidden');
    });

    const closeDrawer = () => {
      mobileDrawer.classList.add('translate-x-full');
      document.body.classList.remove('overflow-hidden');
    };

    if (closeMobileMenu) {
      closeMobileMenu.addEventListener('click', closeDrawer);
    }

    mobileNavLinks.forEach(link => {
      link.addEventListener('click', closeDrawer);
    });
  }

  // 2. Interactive ROI & Ad Spend Calculator
  const adSpendInput = document.getElementById('adSpendInput');
  const spendDisplay = document.getElementById('spendDisplay');
  const industrySelect = document.getElementById('industrySelect');
  const roasMultiplier = document.getElementById('roasMultiplier');
  const roasDisplay = document.getElementById('roasDisplay');

  const calcLeads = document.getElementById('calcLeads');
  const calcRevenue = document.getElementById('calcRevenue');
  const calcROI = document.getElementById('calcROI');
  const calcReach = document.getElementById('calcReach');

  const calculateGrowth = () => {
    if (!adSpendInput || !calcRevenue) return;

    const spend = parseFloat(adSpendInput.value) || 500;
    const roas = parseFloat(roasMultiplier ? roasMultiplier.value : 3.5) || 3.5;
    const industry = industrySelect ? industrySelect.value : 'ecommerce';

    // Update display values
    if (spendDisplay) spendDisplay.textContent = `$${spend.toLocaleString()}`;
    if (roasDisplay) roasDisplay.textContent = `${roas.toFixed(1)}x`;

    // Calculation multipliers based on industry
    let leadCostMultiplier = 15; // default $15/lead
    let cpm = 8; // $8 per 1000 impressions

    if (industry === 'ecommerce') {
      leadCostMultiplier = 10;
      cpm = 6;
    } else if (industry === 'b2b') {
      leadCostMultiplier = 35;
      cpm = 14;
    } else if (industry === 'realestate') {
      leadCostMultiplier = 25;
      cpm = 10;
    } else if (industry === 'saas') {
      leadCostMultiplier = 20;
      cpm = 12;
    }

    const projectedRevenue = Math.round(spend * roas);
    const estimatedLeads = Math.round(spend / leadCostMultiplier);
    const estimatedReach = Math.round((spend / cpm) * 1000);
    const netProfitMultiplier = Math.round(((projectedRevenue - spend) / spend) * 100);

    // Render results
    if (calcRevenue) calcRevenue.textContent = `$${projectedRevenue.toLocaleString()}`;
    if (calcLeads) calcLeads.textContent = `${estimatedLeads.toLocaleString()}+`;
    if (calcROI) calcROI.textContent = `+${netProfitMultiplier}%`;
    if (calcReach) calcReach.textContent = `${(estimatedReach / 1000).toFixed(1)}k+`;
  };

  if (adSpendInput) adSpendInput.addEventListener('input', calculateGrowth);
  if (roasMultiplier) roasMultiplier.addEventListener('input', calculateGrowth);
  if (industrySelect) industrySelect.addEventListener('change', calculateGrowth);

  calculateGrowth();

  // 3. Consultation / Growth Plan Modal
  const openModalBtns = document.querySelectorAll('.open-consultation-modal');
  const auditModal = document.getElementById('auditModal');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const modalServiceInput = document.getElementById('modalServiceInput');

  openModalBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const service = btn.getAttribute('data-service') || 'All-in-One Growth Plan';
      if (modalServiceInput) modalServiceInput.value = service;
      if (auditModal) {
        auditModal.classList.remove('hidden');
        auditModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
      }
    });
  });

  const closeAuditModal = () => {
    if (auditModal) {
      auditModal.classList.add('hidden');
      auditModal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
    }
  };

  if (closeModalBtn) closeModalBtn.addEventListener('click', closeAuditModal);
  if (auditModal) {
    auditModal.addEventListener('click', (e) => {
      if (e.target === auditModal) closeAuditModal();
    });
  }

  // 4. Database Submission & WhatsApp Direct Routing
  const handleLeadSubmit = (formId, isModal = false) => {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = form.querySelector('[name="client_name"]')?.value || 'Client';
      const phone = form.querySelector('[name="client_phone"]')?.value || 'N/A';
      const brand = form.querySelector('[name="brand_name"]')?.value || 'New Venture';
      const service = form.querySelector('[name="service_type"]')?.value || 'Digital Dominance Growth Plan';
      const budget = form.querySelector('[name="budget"]')?.value || '$100 - $300';
      const message = form.querySelector('[name="message"]')?.value || 'I want to scale my brand with NEX-47.';
      const source = isModal ? 'Hero/Header Modal' : 'Main Contact Section';

      // 1. Save to MySQL Database via PHP API
      try {
        fetch('api/submit_lead.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            client_name: name,
            client_phone: phone,
            brand_name: brand,
            service_type: service,
            budget: budget,
            message: message,
            source: source
          })
        }).then(res => res.json()).then(data => {
          console.log('Lead stored in MySQL:', data);
        }).catch(err => {
          console.log('AJAX sync fallback:', err);
        });
      } catch (err) {
        console.error('Database lead save error:', err);
      }

      // 2. Open WhatsApp with formatted brief
      const whatsappNumber = '923495438083'; // Official Agency WhatsApp Number

      const text = encodeURIComponent(
        `⚡ *New Client Inquiry — NEX - 47 CATALYS'S*\n\n` +
        `👤 *Name:* ${name}\n` +
        `📱 *Phone/WhatsApp:* ${phone}\n` +
        `🏢 *Brand/Website:* ${brand}\n` +
        `🎯 *Service Required:* ${service}\n` +
        `💰 *Budget Tier:* ${budget}\n` +
        `💬 *Message:* ${message}\n\n` +
        `_Stored in NEX-47 Database & CRM_`
      );

      const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${text}`;

      // Open WhatsApp in new tab
      window.open(whatsappUrl, '_blank');

      // Success feedback
      showToast('Growth brief saved to database & opening WhatsApp! 🚀');

      if (isModal) {
        setTimeout(() => {
          closeAuditModal();
          form.reset();
        }, 1200);
      } else {
        form.reset();
      }
    });
  };

  handleLeadSubmit('modalAuditForm', true);
  handleLeadSubmit('mainContactForm', false);

  // 5. Toast Notification System
  function showToast(message) {
    let toast = document.getElementById('nexToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'nexToast';
      toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[1000] px-6 py-3 rounded-full bg-[#11121a] border border-[#39ff14] text-white text-sm font-medium shadow-[0_0_25px_rgba(57,255,20,0.4)] flex items-center gap-2 transition-all duration-300 opacity-0 pointer-events-none';
      document.body.appendChild(toast);
    }

    toast.innerHTML = `<span class="h-2 w-2 rounded-full bg-[#39ff14] animate-ping"></span> ${message}`;
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    setTimeout(() => {
      toast.classList.add('opacity-0', 'pointer-events-none');
      toast.classList.remove('opacity-100');
    }, 4000);
  }

  // 6. FAQ Accordion Toggle
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    const trigger = item.querySelector('.faq-trigger');
    const content = item.querySelector('.faq-content');
    const icon = item.querySelector('.faq-icon');

    if (trigger && content) {
      trigger.addEventListener('click', () => {
        const isOpen = !content.classList.contains('hidden');

        // Close all others
        faqItems.forEach(other => {
          const otherContent = other.querySelector('.faq-content');
          const otherIcon = other.querySelector('.faq-icon');
          if (otherContent) otherContent.classList.add('hidden');
          if (otherIcon) otherIcon.classList.remove('rotate-180', 'text-[#39ff14]');
        });

        if (!isOpen) {
          content.classList.remove('hidden');
          if (icon) {
            icon.classList.add('rotate-180', 'text-[#39ff14]');
          }
        }
      });
    }
  });

  // 7. Filter Tabs for 9 Services
  const filterBtns = document.querySelectorAll('.service-filter-btn');
  const serviceCards = document.querySelectorAll('.service-card-item');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => {
        b.classList.remove('bg-[#39ff14]', 'text-[#060608]', 'font-bold', 'border-[#39ff14]');
        b.classList.add('bg-white/5', 'text-white/70', 'border-white/10');
      });

      btn.classList.add('bg-[#39ff14]', 'text-[#060608]', 'font-bold', 'border-[#39ff14]');
      btn.classList.remove('bg-white/5', 'text-white/70', 'border-white/10');

      const filter = btn.getAttribute('data-filter');

      serviceCards.forEach(card => {
        const category = card.getAttribute('data-category');
        if (filter === 'all' || category.includes(filter)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // 8. Sticky Header Scroll Effect
  const mainHeader = document.getElementById('mainHeader');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) {
      mainHeader?.classList.add('bg-[#060608]/90', 'backdrop-blur-md', 'border-white/10', 'shadow-[0_4px_30px_rgba(0,0,0,0.8)]');
      mainHeader?.classList.remove('bg-transparent', 'border-transparent');
    } else {
      mainHeader?.classList.remove('bg-[#060608]/90', 'backdrop-blur-md', 'border-white/10', 'shadow-[0_4px_30px_rgba(0,0,0,0.8)]');
      mainHeader?.classList.add('bg-transparent', 'border-transparent');
    }
  });
});
