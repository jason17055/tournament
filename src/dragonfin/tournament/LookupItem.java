package dragonfin.tournament;

public class LookupItem
{
	Object returnValue;
	String displayValue;

	public LookupItem(Object returnValue)
	{
		this.returnValue = returnValue;
		this.displayValue = (returnValue != null ? returnValue.toString() : "");
	}

	public LookupItem(Object returnValue, String displayValue)
	{
		this.returnValue = returnValue;
		this.displayValue = displayValue;
	}

	@Override
	public String toString()
	{
		return displayValue;
	}

	public static final LookupItem UNLISTED = new LookupItem(new Object() {}, "[Unlisted]");
}
