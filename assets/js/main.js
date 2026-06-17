// ============================================================
// assets/js/main.js — DiemChuan.vn
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  // ==========================================================
  // THÔNG BÁO
  // ==========================================================
  document.querySelectorAll('.alert[role="alert"]').forEach(alertElement => {
    setTimeout(() => {
      if (typeof bootstrap === 'undefined') {
        return;
      }

      const alertInstance =
        bootstrap.Alert.getOrCreateInstance(alertElement);

      alertInstance.close();
    }, 4000);
  });

  // ==========================================================
  // DARK MODE
  // ==========================================================
  const darkButton = document.getElementById('darkBtn');

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    if (!darkButton) {
      return;
    }

    darkButton.innerHTML = theme === 'dark'
      ? '<i class="bi bi-sun-fill"></i>'
      : '<i class="bi bi-moon"></i>';

    darkButton.title = theme === 'dark'
      ? 'Chuyển sang chế độ sáng'
      : 'Chuyển sang chế độ tối';
  }

  const savedTheme = localStorage.getItem('theme') || 'light';

  applyTheme(savedTheme);

  if (darkButton) {
    darkButton.addEventListener('click', () => {
      const currentTheme =
        document.documentElement.getAttribute('data-theme');

      const nextTheme =
        currentTheme === 'dark' ? 'light' : 'dark';

      localStorage.setItem('theme', nextTheme);
      applyTheme(nextTheme);
    });
  }

  // ==========================================================
  // AUTOCOMPLETE TRÊN NAVBAR
  // ==========================================================
  const navSearchInput = document.getElementById('navSearch');
  const navSearchDropdown = document.getElementById('navDrop');

  if (navSearchInput && navSearchDropdown) {
    const apiUrl = navSearchInput.dataset.apiurl;
    const universityUrl = navSearchInput.dataset.uniurl;
    const majorUrl = navSearchInput.dataset.majurl;

    let searchTimer = null;
    let requestController = null;

    function closeNavSearchDropdown() {
      navSearchDropdown.style.display = 'none';
      navSearchDropdown.innerHTML = '';
    }

    function createDropdownHeader(text) {
      const header = document.createElement('div');

      header.className = 'drop-hd';
      header.textContent = text;

      return header;
    }

    function createDropdownLink({
      href,
      label,
      description = ''
    }) {
      const link = document.createElement('a');

      link.className = 'drop-item';
      link.href = href;

      const labelElement = document.createElement('span');
      labelElement.textContent = label;

      link.appendChild(labelElement);

      if (description) {
        const descriptionElement = document.createElement('small');

        descriptionElement.className = 'text-muted ms-1';
        descriptionElement.textContent = description;

        link.appendChild(descriptionElement);
      }

      return link;
    }

    function renderAutocomplete(data) {
      navSearchDropdown.innerHTML = '';

      const universities =
        Array.isArray(data.universities)
          ? data.universities
          : [];

      const majors =
        Array.isArray(data.majors)
          ? data.majors
          : [];

      if (
        universities.length === 0 &&
        majors.length === 0
      ) {
        closeNavSearchDropdown();
        return;
      }

      if (universities.length > 0) {
        navSearchDropdown.appendChild(
          createDropdownHeader('🏛️ Trường đại học')
        );

        universities.forEach(university => {
          const universityLink = createDropdownLink({
            href:
              `${universityUrl}?id=${encodeURIComponent(university.id)}`,
            label: university.name || '',
            description: university.province || ''
          });

          navSearchDropdown.appendChild(universityLink);
        });
      }

      if (majors.length > 0) {
        const majorHeader =
          createDropdownHeader('📚 Ngành học');

        majorHeader.classList.add('mt-1');

        navSearchDropdown.appendChild(majorHeader);

        majors.forEach(major => {
          const majorLink = createDropdownLink({
            href:
              `${majorUrl}?id=${encodeURIComponent(major.id)}`,
            label: major.name || ''
          });

          navSearchDropdown.appendChild(majorLink);
        });
      }

      navSearchDropdown.style.display = 'block';
    }

    navSearchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);

      const keyword = navSearchInput.value.trim();

      if (requestController) {
        requestController.abort();
      }

      if (keyword.length < 2) {
        closeNavSearchDropdown();
        return;
      }

      searchTimer = setTimeout(async () => {
        try {
          requestController = new AbortController();

          const response = await fetch(
            `${apiUrl}?q=${encodeURIComponent(keyword)}`,
            {
              signal: requestController.signal,
              headers: {
                Accept: 'application/json'
              }
            }
          );

          if (!response.ok) {
            throw new Error('Không thể tải dữ liệu tìm kiếm.');
          }

          const data = await response.json();

          renderAutocomplete(data);
        } catch (error) {
          if (error.name !== 'AbortError') {
            closeNavSearchDropdown();
            console.error(error);
          }
        }
      }, 280);
    });

    navSearchInput.addEventListener('focus', () => {
      if (navSearchDropdown.children.length > 0) {
        navSearchDropdown.style.display = 'block';
      }
    });

    document.addEventListener('click', event => {
      const clickedInsideInput =
        navSearchInput.contains(event.target);

      const clickedInsideDropdown =
        navSearchDropdown.contains(event.target);

      if (!clickedInsideInput && !clickedInsideDropdown) {
        closeNavSearchDropdown();
      }
    });
  }

  // ==========================================================
  // TRANG CHỦ
  // ==========================================================
  const recentSearchKey =
    'diemchuan_recent_searches';

  const universityForm =
    document.getElementById('form-uni');

  const majorForm =
    document.getElementById('form-major');

  const universityInput =
    document.getElementById('heroUniversityInput');

  const majorSelect =
    document.getElementById('heroMajorSelect');

  // ----------------------------------------------------------
  // ĐẾM SỐ LIỆU TĂNG DẦN
  // ----------------------------------------------------------
  const counterElements =
    document.querySelectorAll('[data-counter]');

  function animateCounter(element) {
    if (element.dataset.animated === 'true') {
      return;
    }

    const target = Number(element.dataset.counter || 0);

    if (!Number.isFinite(target) || target < 0) {
      return;
    }

    element.dataset.animated = 'true';

    const duration = 900;
    const startTime = performance.now();
    const numberFormatter =
      new Intl.NumberFormat('vi-VN');

    function updateCounter(currentTime) {
      const elapsed = currentTime - startTime;

      const progress =
        Math.min(elapsed / duration, 1);

      const easedProgress =
        1 - Math.pow(1 - progress, 3);

      const currentValue =
        Math.round(target * easedProgress);

      element.textContent =
        numberFormatter.format(currentValue);

      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      }
    }

    requestAnimationFrame(updateCounter);
  }

  if (
    counterElements.length > 0 &&
    'IntersectionObserver' in window
  ) {
    const counterObserver =
      new IntersectionObserver(
        (entries, observer) => {
          entries.forEach(entry => {
            if (!entry.isIntersecting) {
              return;
            }

            animateCounter(entry.target);
            observer.unobserve(entry.target);
          });
        },
        {
          threshold: 0.4
        }
      );

    counterElements.forEach(element => {
      counterObserver.observe(element);
    });
  } else {
    counterElements.forEach(animateCounter);
  }

  // ----------------------------------------------------------
  // HIỆU ỨNG XUẤT HIỆN KHI CUỘN
  // ----------------------------------------------------------
  const revealElements =
    document.querySelectorAll('.reveal-up');

  revealElements.forEach(element => {
    element.classList.add('reveal-pending');
  });

  if (
    revealElements.length > 0 &&
    'IntersectionObserver' in window
  ) {
    const revealObserver =
      new IntersectionObserver(
        (entries, observer) => {
          entries.forEach(entry => {
            if (!entry.isIntersecting) {
              return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          });
        },
        {
          threshold: 0.12
        }
      );

    revealElements.forEach(element => {
      revealObserver.observe(element);
    });
  } else {
    revealElements.forEach(element => {
      element.classList.add('is-visible');
    });
  }

  // ----------------------------------------------------------
  // TRẠNG THÁI ĐANG TÌM KIẾM
  // ----------------------------------------------------------
  function showFormLoading(form) {
    if (!form) {
      return;
    }

    const submitButton =
      form.querySelector('.js-submit-btn');

    if (!submitButton) {
      return;
    }

    submitButton.disabled = true;
    submitButton.classList.add('is-loading');

    submitButton.innerHTML = `
      <span
        class="spinner-border spinner-border-sm me-2"
        role="status"
        aria-hidden="true">
      </span>
      Đang tìm...
    `;
  }

  document
    .querySelectorAll('.js-home-search-form')
    .forEach(form => {
      form.addEventListener('submit', () => {
        showFormLoading(form);
      });
    });

  // ----------------------------------------------------------
  // LỊCH SỬ TÌM KIẾM
  // ----------------------------------------------------------
  function getRecentSearches() {
    try {
      const storedData =
        localStorage.getItem(recentSearchKey);

      if (!storedData) {
        return [];
      }

      const parsedData = JSON.parse(storedData);

      return Array.isArray(parsedData)
        ? parsedData
        : [];
    } catch (error) {
      console.error(
        'Không thể đọc lịch sử tìm kiếm:',
        error
      );

      return [];
    }
  }

  function saveRecentSearch(searchItem) {
    if (
      !searchItem ||
      !searchItem.type ||
      !searchItem.value ||
      !searchItem.label
    ) {
      return;
    }

    const oldItems = getRecentSearches();

    const filteredItems =
      oldItems.filter(oldItem => {
        const sameType =
          oldItem.type === searchItem.type;

        const sameValue =
          String(oldItem.value) ===
          String(searchItem.value);

        return !(sameType && sameValue);
      });

    const newItems = [
      searchItem,
      ...filteredItems
    ].slice(0, 6);

    localStorage.setItem(
      recentSearchKey,
      JSON.stringify(newItems)
    );

    renderRecentSearches();
  }

  function openRecentSearch(searchItem) {
    if (searchItem.type === 'uni') {
      if (!universityForm || !universityInput) {
        return;
      }

      switchTab('uni');

      universityInput.value =
        searchItem.value;

      if (
        typeof universityForm.requestSubmit ===
        'function'
      ) {
        universityForm.requestSubmit();
      } else {
        universityForm.submit();
      }

      return;
    }

    if (searchItem.type === 'major') {
      if (!majorForm || !majorSelect) {
        return;
      }

      const optionExists = Array
        .from(majorSelect.options)
        .some(option => {
          return String(option.value) ===
            String(searchItem.value);
        });

      if (!optionExists) {
        return;
      }

      switchTab('major');

      majorSelect.value =
        searchItem.value;

      if (
        typeof majorForm.requestSubmit ===
        'function'
      ) {
        majorForm.requestSubmit();
      } else {
        majorForm.submit();
      }
    }
  }

  function renderRecentSearches() {
    const wrapper =
      document.getElementById(
        'recentSearchesWrap'
      );

    const list =
      document.getElementById(
        'recentSearchList'
      );

    if (!wrapper || !list) {
      return;
    }

    const searchItems =
      getRecentSearches();

    list.innerHTML = '';

    if (searchItems.length === 0) {
      wrapper.classList.add('d-none');
      return;
    }

    wrapper.classList.remove('d-none');

    searchItems.forEach(searchItem => {
      const button =
        document.createElement('button');

      button.type = 'button';
      button.className =
        'recent-search-chip';

      const icon =
        document.createElement('i');

      icon.className =
        searchItem.type === 'major'
          ? 'bi bi-book'
          : 'bi bi-building';

      const label =
        document.createElement('span');

      label.textContent =
        searchItem.label;

      button.appendChild(icon);
      button.appendChild(label);

      button.addEventListener('click', () => {
        openRecentSearch(searchItem);
      });

      list.appendChild(button);
    });
  }

  // Lưu từ khóa tìm trường
  if (universityForm && universityInput) {
    universityForm.addEventListener('submit', () => {
      const keyword =
        universityInput.value.trim();

      if (!keyword) {
        return;
      }

      saveRecentSearch({
        type: 'uni',
        value: keyword,
        label: keyword
      });
    });
  }

  // Lưu ngành được chọn
  if (majorForm && majorSelect) {
    majorForm.addEventListener('submit', () => {
      const selectedOption =
        majorSelect.options[
          majorSelect.selectedIndex
        ];

      if (
        !selectedOption ||
        majorSelect.value === '0' ||
        majorSelect.value === ''
      ) {
        return;
      }

      saveRecentSearch({
        type: 'major',
        value: majorSelect.value,
        label: selectedOption.text.trim()
      });
    });

    majorSelect.addEventListener('change', () => {
      const selectedOption =
        majorSelect.options[
          majorSelect.selectedIndex
        ];

      if (
        !selectedOption ||
        majorSelect.value === '0' ||
        majorSelect.value === ''
      ) {
        return;
      }

      saveRecentSearch({
        type: 'major',
        value: majorSelect.value,
        label: selectedOption.text.trim()
      });
    });
  }

  // Tìm nhanh bằng nút ngành phổ biến
  document
    .querySelectorAll('[data-major-id]')
    .forEach(button => {
      button.addEventListener('click', () => {
        if (!majorSelect || !majorForm) {
          return;
        }

        const majorId =
          button.dataset.majorId;

        const optionExists = Array
          .from(majorSelect.options)
          .some(option => {
            return String(option.value) ===
              String(majorId);
          });

        if (!optionExists) {
          return;
        }

        switchTab('major');

        majorSelect.value = majorId;

        const selectedOption =
          majorSelect.options[
            majorSelect.selectedIndex
          ];

        saveRecentSearch({
          type: 'major',
          value: majorId,
          label:
            selectedOption?.text.trim() ||
            button.textContent.trim()
        });

        if (
          typeof majorForm.requestSubmit ===
          'function'
        ) {
          majorForm.requestSubmit();
        } else {
          majorForm.submit();
        }
      });
    });

  // Xóa lịch sử tìm kiếm
  const clearRecentButton =
    document.getElementById(
      'clearRecentSearches'
    );

  if (clearRecentButton) {
    clearRecentButton.addEventListener(
      'click',
      () => {
        localStorage.removeItem(
          recentSearchKey
        );

        renderRecentSearches();
      }
    );
  }

  renderRecentSearches();
});

// ============================================================
// CHUYỂN TAB TÌM KIẾM TRANG CHỦ
// ============================================================
function switchTab(tab) {
  const universityForm =
    document.getElementById('form-uni');

  const majorForm =
    document.getElementById('form-major');

  const universityTab =
    document.getElementById('tab-uni');

  const majorTab =
    document.getElementById('tab-major');

  if (
    !universityForm ||
    !majorForm ||
    !universityTab ||
    !majorTab
  ) {
    return;
  }

  const isUniversityTab =
    tab === 'uni';

  universityForm.classList.toggle(
    'd-none',
    !isUniversityTab
  );

  majorForm.classList.toggle(
    'd-none',
    isUniversityTab
  );

  universityTab.classList.toggle(
    'btn-light',
    isUniversityTab
  );

  universityTab.classList.toggle(
    'btn-outline-light',
    !isUniversityTab
  );

  majorTab.classList.toggle(
    'btn-light',
    !isUniversityTab
  );

  majorTab.classList.toggle(
    'btn-outline-light',
    isUniversityTab
  );

  universityTab.setAttribute(
    'aria-pressed',
    String(isUniversityTab)
  );

  majorTab.setAttribute(
    'aria-pressed',
    String(!isUniversityTab)
  );

  if (isUniversityTab) {
    const universityInput =
      document.getElementById(
        'heroUniversityInput'
      );

    universityInput?.focus();
  } else {
    const majorSelect =
      document.getElementById(
        'heroMajorSelect'
      );

    majorSelect?.focus();
  }
}

// Để onclick trong HTML có thể gọi được
window.switchTab = switchTab;

// ============================================================
// XÁC NHẬN XÓA
// ============================================================
function confirmDelete(url, name) {
  const itemName =
    name || 'mục này';

  const accepted = window.confirm(
    `Xóa "${itemName}"?\nKhông thể hoàn tác!`
  );

  if (accepted) {
    window.location.href = url;
  }
}

window.confirmDelete = confirmDelete;

// ============================================================
// XEM TRƯỚC LOGO
// ============================================================
function previewLogo(input, imageId) {
  if (
    !input ||
    !input.files ||
    !input.files[0]
  ) {
    return;
  }

  const image =
    document.getElementById(imageId);

  if (!image) {
    return;
  }

  const file = input.files[0];

  if (!file.type.startsWith('image/')) {
    window.alert(
      'Vui lòng chọn đúng file hình ảnh.'
    );

    input.value = '';
    return;
  }

  const fileReader = new FileReader();

  fileReader.addEventListener('load', event => {
    image.src = event.target.result;
    image.classList.remove('d-none');
  });

  fileReader.readAsDataURL(file);
}

window.previewLogo = previewLogo;

// ============================================================
// BIỂU ĐỒ ĐƯỜNG
// ============================================================
function chartLine(
  elementId,
  labels,
  datasets
) {
  const canvas =
    document.getElementById(elementId);

  if (
    !canvas ||
    typeof Chart === 'undefined'
  ) {
    return null;
  }

  return new Chart(canvas, {
    type: 'line',

    data: {
      labels,
      datasets
    },

    options: {
      responsive: true,
      maintainAspectRatio: true,

      interaction: {
        mode: 'index',
        intersect: false
      },

      plugins: {
        legend: {
          position: 'bottom',

          labels: {
            boxWidth: 12,

            font: {
              size: 11
            }
          }
        },

        tooltip: {
          callbacks: {
            label(context) {
              const value =
                context.parsed.y;

              return `${context.dataset.label}: ${value ?? 'Không có dữ liệu'}`;
            }
          }
        }
      },

      scales: {
        y: {
          suggestedMin: 15,
          suggestedMax: 30,

          title: {
            display: true,
            text: 'Điểm chuẩn'
          }
        }
      }
    }
  });
}

window.chartLine = chartLine;

// ============================================================
// BIỂU ĐỒ CỘT
// ============================================================
function chartBar(
  elementId,
  labels,
  data,
  label = 'Điểm'
) {
  const canvas =
    document.getElementById(elementId);

  if (
    !canvas ||
    typeof Chart === 'undefined'
  ) {
    return null;
  }

  return new Chart(canvas, {
    type: 'bar',

    data: {
      labels,

      datasets: [
        {
          label,
          data,
          backgroundColor:
            'rgba(26, 86, 219, .82)',
          borderRadius: 6
        }
      ]
    },

    options: {
      responsive: true,
      maintainAspectRatio: true,

      plugins: {
        legend: {
          display: false
        }
      },

      scales: {
        y: {
          beginAtZero: false,
          suggestedMin: 0
        }
      }
    }
  });
}

window.chartBar = chartBar;

// ============================================================
// BIỂU ĐỒ TRÒN
// ============================================================
function chartDoughnut(
  elementId,
  labels,
  data
) {
  const canvas =
    document.getElementById(elementId);

  if (
    !canvas ||
    typeof Chart === 'undefined'
  ) {
    return null;
  }

  return new Chart(canvas, {
    type: 'doughnut',

    data: {
      labels,

      datasets: [
        {
          data,

          backgroundColor: [
            '#1a56db',
            '#10b981',
            '#f59e0b',
            '#8b5cf6',
            '#ef4444',
            '#64748b'
          ],

          borderWidth: 0
        }
      ]
    },

    options: {
      responsive: true,
      maintainAspectRatio: true,

      plugins: {
        legend: {
          position: 'bottom',

          labels: {
            boxWidth: 12,

            font: {
              size: 11
            }
          }
        }
      }
    }
  });
}

window.chartDoughnut = chartDoughnut;

// ============================================================
// BIỂU ĐỒ CỘT NHÓM
// ============================================================
function chartBar2(
  elementId,
  labels,
  datasets
) {
  const canvas =
    document.getElementById(elementId);

  if (
    !canvas ||
    typeof Chart === 'undefined'
  ) {
    return null;
  }

  return new Chart(canvas, {
    type: 'bar',

    data: {
      labels,
      datasets
    },

    options: {
      responsive: true,
      maintainAspectRatio: true,

      interaction: {
        mode: 'index',
        intersect: false
      },

      plugins: {
        legend: {
          position: 'bottom',

          labels: {
            boxWidth: 12,

            font: {
              size: 11
            }
          }
        }
      },

      scales: {
        y: {
          suggestedMin: 15,
          suggestedMax: 30,

          title: {
            display: true,
            text: 'Điểm chuẩn'
          }
        }
      }
    }
  });
}

window.chartBar2 = chartBar2;