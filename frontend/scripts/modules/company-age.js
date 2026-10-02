/**
 * Returns complete anniversary years between two local calendar dates.
 * Times are normalized to noon to avoid daylight-saving boundary surprises.
 */
export function getCompletedYears(foundedOn, today) {
  if (!(foundedOn instanceof Date) || Number.isNaN(foundedOn.getTime())) {
    throw new TypeError("A data de fundação precisa ser uma data válida.");
  }
  if (!(today instanceof Date) || Number.isNaN(today.getTime())) {
    throw new TypeError("A data atual precisa ser uma data válida.");
  }

  let years = today.getFullYear() - foundedOn.getFullYear();
  const anniversaryPending =
    today.getMonth() < foundedOn.getMonth() ||
    (today.getMonth() === foundedOn.getMonth() &&
      today.getDate() < foundedOn.getDate());

  if (anniversaryPending) years -= 1;
  return Math.max(0, years);
}
