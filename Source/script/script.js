"use strict";

console.log("SCRIPT.JS E VCHITAN!");

// ======================================
// ЕЛЕМЕНТИ ЗА ПРЕБАРУВАЊЕ
// ======================================

const searchForm = document.getElementById("searchForm");
const keywordInput = document.getElementById("keyword");
const locationInput = document.getElementById("location");
const categorySelect = document.getElementById("category");

const jobCards = document.querySelectorAll(".job-card");
const resultsCount = document.getElementById("resultsCount");
const emptyState = document.getElementById("emptyState");
const resetSearch = document.getElementById("resetSearch");

// ======================================
// ФУНКЦИЈА ЗА НОРМАЛИЗИРАЊЕ ТЕКСТ
// ======================================

function normalizeText(text) {
    return text.toLocaleLowerCase("mk").trim();
}

// ======================================
// ПРЕБАРУВАЊЕ НА РАБОТНИ ОГЛАСИ
// ======================================

// Оваа функционалност се активира само
// ако постојат сите потребни HTML елементи.

if (searchForm && keywordInput && locationInput && categorySelect) {

    function searchJobs(shouldScroll = true) {

        const keyword = normalizeText(keywordInput.value);
        const location = normalizeText(locationInput.value);
        const category = categorySelect.value;

        const synonyms = {
            developer: ["developer", "software engineer", "it"],
            designer: ["designer", "design"],
            marketing: ["marketing", "digital marketing"]
        };

        const searchTerms = synonyms[keyword] || [keyword];

        let visibleJobs = 0;

        jobCards.forEach(function(job) {

            const title = normalizeText(job.dataset.title || "");
            const company = normalizeText(job.dataset.company || "");
            const jobLocation = normalizeText(job.dataset.location || "");
            const jobCategory = job.dataset.category || "";

            const matchesKeyword =
                keyword === "" ||
                searchTerms.some(function(term) {
                    return (
                        title.includes(term) ||
                        company.includes(term) ||
                        jobCategory.includes(term)
                    );
                });

            const matchesLocation =
                location === "" || jobLocation.includes(location);

            const matchesCategory =
                category === "" || jobCategory === category;

            const isMatch =
                matchesKeyword &&
                matchesLocation &&
                matchesCategory;

            job.hidden = !isMatch;

            if (isMatch) {
                visibleJobs++;
            }
        });

        if (resultsCount) {
            resultsCount.textContent =
                visibleJobs +
                (visibleJobs === 1 ? " оглас" : " огласи");
        }

        if (emptyState) {
            emptyState.hidden = visibleJobs !== 0;
        }

        if (shouldScroll) {
            const jobsSection = document.getElementById("jobs");

            if (jobsSection) {
                jobsSection.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }
        }
    }

    // Пребарување преку формата

    searchForm.addEventListener("submit", function(event) {
        event.preventDefault();
        searchJobs();
    });

    // Популарни пребарувања

    document.querySelectorAll(".search-tag").forEach(function(button) {

        button.addEventListener("click", function() {
            keywordInput.value = button.dataset.search;
            searchJobs();
        });

    });

    // Пребарување преку категории

    document.querySelectorAll(".category-card").forEach(function(button) {

        button.addEventListener("click", function() {
            categorySelect.value = button.dataset.category;
            searchJobs();
        });

    });

    // Ресетирање на филтрите

    if (resetSearch) {

        resetSearch.addEventListener("click", function() {

            keywordInput.value = "";
            locationInput.value = "";
            categorySelect.value = "";

            searchJobs();

        });

    }

} else {

    console.log("Пребарувањето не е активно на оваа страница.");

}

// ======================================
// НАЈАВА
// ======================================

const loginBtn = document.getElementById("loginBtn");

if (loginBtn) {

    loginBtn.addEventListener("click", function() {
        window.location.href = "../PHP/login.php";
    });

}

// ======================================
// РЕГИСТРАЦИЈА
// ======================================

function openRegistration() {
    window.location.href = "../PHP/register.php";
}

const registerBtn = document.getElementById("registerBtn");

if (registerBtn) {
    registerBtn.addEventListener("click", openRegistration);
}

const sidebarRegister = document.getElementById("sidebarRegister");

if (sidebarRegister) {
    sidebarRegister.addEventListener("click", openRegistration);
}
