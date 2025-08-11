const tbody_baixar_ementas = document.querySelector("#tbody_baixar_ementas");
const input_baixar_ementas = document.querySelector("#input_baixar_ementas");
const select_baixar_ementas = document.querySelector("#select_baixar_ementas");
const button_baixar_ementas = document.querySelector("#button_baixar_ementas");
const loading_container_baixar_ementas = document.querySelector(
  "#loading_container_baixar_ementas"
);

select_baixar_ementas.addEventListener("change", function (event) {
  filterTableItens();
});

input_baixar_ementas.addEventListener("keyup", function (event) {
  if (event.key === "Enter") {
    filterTableItens();
  }
});

button_baixar_ementas.addEventListener("click", filterTableItens);

function filterTableItens() {
  // To not block the DOM
  const filter_classrooms_promise = new Promise((resolve, reject) => {
    const input_filter_query = input_baixar_ementas.value;
    const select_filter_query = select_baixar_ementas.value;

    const tbody_trs_baixar_ementas = document.querySelectorAll(
      "#tbody_baixar_ementas tr"
    );

    for (const tr of tbody_trs_baixar_ementas) {
      if (select_filter_query != "" && input_filter_query != "") {
        console.log("Both");
        if (
          tr.childNodes[0].innerText
            .toLowerCase()
            .indexOf(select_filter_query.toLowerCase()) > -1 &&
          tr.childNodes[1].innerText
            .toLowerCase()
            .indexOf(input_filter_query.toLowerCase()) > -1
        )
          tr.classList.remove("d-none");
        else tr.classList.add("d-none");
      } else if (select_filter_query != "") {
        console.log("select");

        if (
          tr.childNodes[0].innerText
            .toLowerCase()
            .indexOf(select_filter_query.toLowerCase()) > -1
        )
          tr.classList.remove("d-none");
        else tr.classList.add("d-none");
      } else {
        console.log("input");

        if (
          tr.childNodes[1].innerText
            .toLowerCase()
            .indexOf(input_filter_query.toLowerCase()) > -1
        )
          tr.classList.remove("d-none");
        else tr.classList.add("d-none");
      }
    }

    resolve(true);
  });

  filter_classrooms_promise.then(() => {});
}
