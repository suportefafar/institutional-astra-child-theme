const tbody_mapa_sala = document.querySelector("#tbody_mapa_sala");
const input_mapa_sala = document.querySelector("#input_mapa_sala");
const button_mapa_sala = document.querySelector("#button_mapa_sala");
const loading_container_mapa_sala = document.querySelector(
  "#loading_container_mapa_sala"
);

input_mapa_sala.addEventListener("keyup", function (event) {
  if (event.key === "Enter") {
    filterTableItens();
  }
});

button_mapa_sala.addEventListener("click", filterTableItens);

function filterTableItens() {
  // To not block the DOM
  const filter_classrooms_promise = new Promise((resolve, reject) => {
    const search_query = input_mapa_sala.value;

    const tbody_trs_mapa_sala = document.querySelectorAll(
      "#tbody_mapa_sala tr"
    );

    for (const tr of tbody_trs_mapa_sala) {
      if (
        tr.childNodes[0].innerText
          .toLowerCase()
          .indexOf(search_query.toLowerCase()) > -1
      )
        tr.classList.remove("d-none");
      else tr.classList.add("d-none");
    }

    resolve(true);
  });

  filter_classrooms_promise.then(() => {});
}
