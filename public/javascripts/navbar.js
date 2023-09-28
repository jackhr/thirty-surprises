const headerEl = document.getElementsByTagName('header')[0];
const linkDivEl = document.getElementById('hamburger-links-div');
const HBIcon = document.getElementById('hamburger-icon');

HBIcon.onclick = (evt) => {
  if (headerEl.className === "open-menu") {
    headerEl.className = "";
    evt.target.src = "/images/hamburger-icon-open.svg.png";
    linkDivEl.style.display = "none";
  } else {
    headerEl.className = "open-menu";
    evt.target.src = "/images/hamburger-icon-close.webp";
    linkDivEl.style.display = "flex";
  }
}